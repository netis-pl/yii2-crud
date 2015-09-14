<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use netis\utils\crud\Action;
use netis\utils\crud\ActiveRecord;
use netis\utils\web\EnumCollection;
use netis\utils\web\Formatter;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;
use yii\db\Query;

trait ActiveSearchTrait
{
    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @param \yii\db\ActiveQuery $query
     * @param Sort|array $sort
     * @param Pagination|array $pagination
     * @return ActiveDataProvider
     */
    public function search($params, \yii\db\ActiveQuery $query = null, $sort = [], $pagination = [])
    {
        if ($query === null) {
            /** @var ActiveQuery $query */
            $query = self::find();
        }
        if ($query instanceof ActiveQuery && isset($params['search'])) {
            $query->quickSearchPhrase = $params['search'];
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => is_array($sort) ? array_merge($this->getSort($query), $sort) : $sort,
            'pagination' => $pagination,
        ]);

        $this->getSearchFilters($params, $query);

        return $dataProvider;
    }

    /**
     * Creates a Sort object configuration using query default order.
     * @param ActiveQuery $query
     * @return array
     */
    private function getSort($query)
    {
        $defaults = $query instanceof ActiveQuery ? $query->getDefaultOrderColumns() : [];
        $sort = [
            'enableMultiSort' => true,
            'attributes' => [],
            'defaultOrder' => $defaults,
        ];

        foreach ($this->attributes() as $attribute) {
            $sort['attributes'][$attribute] = [
                'asc' => array_merge([$attribute => SORT_ASC], $defaults),
                'desc' => array_merge([$attribute => SORT_DESC], $defaults),
            ];
        }
        return $sort;
    }

    /**
     * @return array contains two arrays: hasOne and hasMany relation names.
     */
    private function getRelationTypes()
    {
        $result = [[], []];
        foreach ($this->relations() as $name) {
            $relation = $this->getRelation($name);
            $result[$relation->multiple ? 1 : 0][] = $name;
        }
        return $result;
    }

    /**
     * @inheritdoc
     * HasOne relations are never safe, even if they have validation rules.
     */
    public function safeAttributes()
    {
        list($hasOneRelations, $hasManyRelations) = $this->getRelationTypes();
        // copied from yii\base\Model because parent::safeAttributes() already filters all relations
        $scenario = $this->getScenario();
        $scenarios = $this->scenarios();
        if (!isset($scenarios[$scenario])) {
            return [];
        }
        $attributes = [];
        foreach ($scenarios[$scenario] as $attribute) {
            if ($attribute[0] !== '!') {
                $attributes[] = $attribute;
            }
        }
        // end of copy

        return array_diff($attributes, $hasOneRelations);
    }

    /**
     * @param string $attribute
     * @param string $value
     * @param array $formats
     * @param string $tablePrefix
     * @param bool $hasILike
     * @return array in format supported by Query::where()
     */
    protected function getAttributeCondition($attribute, $value, $formats, $tablePrefix, $hasILike)
    {
        $columnName = $tablePrefix . '.' . $this->getDb()->getSchema()->quoteSimpleColumnName($attribute);
        switch ($formats[$attribute]) {
            default:
                if (!is_string($value) || strlen($value) < 2 || ($value{0} !== '>' && $value{0} !== '<')) {
                    return [$columnName => $value];
                }

                $op = substr($value, 0, $value{1} !== '=' ? 1 : 2);
                $value = substr($value, strlen($op));
                if (trim($value) === '') {
                    return [];
                }

                return [$op, $columnName, $value];
            case 'string':
            case 'text':
            case 'email':
            case 'url':
                if (is_array($value)) {
                    return [$columnName => $value];
                }
                return [$hasILike ? 'ilike' : 'like', $columnName, $value];
            case 'json':
                $subquery = (new Query())
                    ->select(1)
                    ->from('json_array_elements(' . $columnName . ') a')
                    ->where([$hasILike ? 'ilike' : 'like', 'a::text', $value]);
                return ['exists', $subquery];
        }
    }

    /**
     * Only hasMany relations should be ever marked as valid attributes.
     * @param string $attribute relation name
     * @param array $value
     * @return array an IN condition with a subquery
     */
    protected function getRelationCondition($attribute, $value)
    {
        /** @var \yii\db\ActiveQuery $relation */
        $relation = $this->getRelation($attribute);
        /** @var \yii\db\ActiveRecord $relationClass */
        $relationClass = $relation->modelClass;
        if ($relation->via !== null) {
            /* @var $viaRelation \yii\db\ActiveQuery */
            $viaRelation = is_array($relation->via) ? $relation->via[1] : $relation->via;
            /** @var \yii\db\ActiveRecord $viaClass */
            $viaClass = $viaRelation->modelClass;
            $subquery = (new Query)
                ->select(array_map(function ($key) {
                    return 'j.' . $key;
                }, array_keys($viaRelation->link)))
                ->from(['t' => $relationClass::tableName()])
                ->innerJoin(['j' => $viaClass::tableName()], implode(' AND ', array_map(function ($leftKey, $rightKey) {
                    return 't.' . $leftKey .' = j.' . $rightKey;
                }, array_keys($relation->link), array_values($relation->link))))
                ->where(['IN', array_map(function ($key) {
                    return 't.' . $key;
                }, $relationClass::primaryKey()), $value]);
            $linkKeys = array_values($viaRelation->link);
        } else {
            $subquery = (new Query)
                ->select(array_keys($relation->link))
                ->from(['t' => $relationClass::tableName()])
                ->where(['IN', array_map(function ($key) {
                    return 't.' . $key;
                }, $relationClass::primaryKey()), $value]);
            $linkKeys = array_values($relation->link);
        }
        return ['IN', $linkKeys, $subquery];
    }

    /**
     * Assigns specified token to specified attributes and validates
     * current model to filter the values. Then, creates search condition.
     * @param  string $token     one search token extracted from whole term
     * @param  array $attributes attributes safe to search
     * @param  string $tablePrefix
     * @return array all conditions joined with OR operator, should be merged with main query object
     */
    public function processSearchToken($token, array $attributes, $tablePrefix = null)
    {
        /** @var \yii\db\Schema $schema */
        $schema = $this->getDb()->getSchema();

        $tablePrefix = $schema->quoteSimpleTableName($tablePrefix === null ? 't' : $tablePrefix);
        $conditions = ['or'];
        $formats = $this->attributeFormats();
        Yii::trace(
            'Processing token ' . $token . ', safe attributes: ' . print_r($attributes, true),
            'netis.crud.ActiveRecord'
        );
        // to support searching in enums token must be first translated to matching values
        $plainAttributes = [];
        /** @var EnumCollection $enums */
        $enums = Yii::$app->formatter instanceof Formatter ? Yii::$app->formatter->getEnums() : null;
        foreach ($attributes as $attribute) {
            if (!isset($formats[$attribute]) || $enums === null
                || is_array($formats[$attribute]) || !$enums->has($formats[$attribute])
            ) {
                $plainAttributes[$attribute] = $token;
                continue;
            }

            // do a linear search in map values and then use matching in query criteria
            $matching = [];
            foreach ($enums->get($formats[$attribute]) as $key => $label) {
                if (mb_stripos($label, $token, 0, 'UTF-8') !== false) {
                    $matching[] = $key;
                }
            }
            if (!empty($matching)) {
                // don't validate, because rules only allow one value anyway
                $conditions[] = ['in', $tablePrefix.'.'.$schema->quoteSimpleColumnName($attribute), $matching];
            }
        }
        $oldAttributes = $this->getAttributes($attributes);
        $this->setAttributes($plainAttributes);
        $this->validate($attributes);
        $validAttributes = array_diff($attributes, array_keys($this->getErrors()));
        Yii::trace(
            'Processing token ' . $token . ', validated in: ' . print_r($validAttributes, true),
            'application.model.NetActiveRecord'
        );
        $attributeValues = $this->getAttributes($validAttributes);
        $hasILike = $this->getDb()->driverName === 'pgsql';
        foreach ($validAttributes as $attribute) {
            $value = $attributeValues[$attribute];
            if (empty($value) || !isset($formats[$attribute])
                || ($enums !== null && !is_array($formats[$attribute]) && $enums->has($formats[$attribute]))
            ) {
                continue;
            }

            $conditions[] = $this->getAttributeCondition($attribute, $value, $formats, $tablePrefix, $hasILike);
        }
        $this->setAttributes($oldAttributes);

        return $conditions !== ['or'] ? $conditions : null;
    }

    /**
     * Adds a condition to search in relations using subquery.
     * @todo this should be called for each token, to group their conditions with OR and group token groups with AND
     *
     * @param \yii\db\ActiveQuery $query
     * @param  array $tokens             all search tokens extracted from term
     * @param  array $relationAttributes array of string(relation name) => array(
     *                                       'model' => netis\utils\crud\ActiveRecord,
     *                                       'searchModel' => netis\utils\db\ActiveSearchTrait,
     *                                       'attributes' => array
     *                                   )
     * @return array conditions to add to $query
     */
    protected function processSearchRelated(\yii\db\ActiveQuery $query, array $tokens, array $relationAttributes)
    {
        $allConditions = ['or'];
        foreach ($relationAttributes as $relationName => $relation) {
            /**
             * @todo optimize this (check first, don't want to loose another battle with PostgreSQL query planner):
             * - for BELONGS_TO check fk against subquery
             * - for HAS_MANY and HAS_ONE check pk against subquery
             * - for MANY_MANY join only to pivot table and check its fk agains subquery
             */
            $query->joinWith([$relationName => function ($query) {
                return $query->select(false);
            }]);
            $query->distinct = true;
            $conditions = ['and'];
            foreach ($tokens as $token) {
                if (($condition = $relation['searchModel']->processSearchToken($token, $relation['attributes'], $relationName)) !== null) {
                    $conditions[] = $condition;
                }
            }
            if ($conditions !== ['and']) {
                $allConditions[] = $conditions;
            }
        }

        return $allConditions !== ['or'] ? $allConditions : null;
    }

    /**
     * Use one value to compare against all columns.
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function getQuickSearchFilters(\yii\db\ActiveQuery $query)
    {
        if (!$query instanceof ActiveQuery) {
            return $query;
        }
        $searchPhrase = array_filter(array_map('trim', explode(',', $query->quickSearchPhrase)));
        if (count($searchPhrase) === 2 && (string)intval($searchPhrase[0]) === $searchPhrase[0]
            && (string)intval($searchPhrase[1]) === $searchPhrase[1]
        ) {
            // special case, whole term is just one digit with decimal separator
            $searchPhrase = [trim($query->quickSearchPhrase)];
        }
        // skip foreign keys, relations are search in other way
        $foreignKeys = array_map(function ($foreignKey) {
            array_shift($foreignKey);
            return array_keys($foreignKey);
        }, $this->getTableSchema()->foreignKeys);
        $allAttributes = !empty($foreignKeys) ? array_diff(
            $this->safeAttributes(),
            call_user_func_array('array_merge', $foreignKeys)
        ) : $this->safeAttributes();
        $safeAttributes = [];
        $relationAttributes = [];
        $relations = $this->relations();

        foreach ($allAttributes as $attribute) {
            if (($pos = strpos($attribute, '.')) === false && !in_array($attribute, $relations)) {
                $safeAttributes[] = $attribute;
                continue;
            }
            if ($pos === false) {
                $relationName = $attribute;
            } else {
                $relationName = substr($attribute, 0, $pos);
            }
            /** @var \yii\db\ActiveQuery $activeRelation */
            $activeRelation = $this->getRelation($relationName);
            /** @var ActiveRecord $relationModel */
            $relationModel = new $activeRelation->modelClass();
            $relationModel->scenario = $this->scenario;

            $parts = explode('\\', $activeRelation->modelClass);
            $modelClass = array_pop($parts);
            $namespace = implode('\\', $parts);
            $searchModelClass = $namespace . '\\search\\' . $modelClass;
            $relationSearchModel = new $searchModelClass;

            if (!isset($relationAttributes[$relationName])) {
                $relationAttributes[$relationName] = [
                    'model'      => $relationModel,
                    'searchModel'=> $relationSearchModel,
                    'attributes' => [],
                ];
            }
            if ($pos === false) {
                /** @var LabelsBehavior $labelsBehavior */
                $labelsBehavior = $relationModel->getBehavior('labels');
                foreach ($labelsBehavior->attributes as $rcAttribute) {
                    $relationAttributes[$relationName]['attributes'][] = $rcAttribute;
                }
            } else {
                $relationAttributes[$relationName]['attributes'][] = substr($attribute, $pos + 1);
            }
        }
        $conditions = ['or'];
        foreach ($searchPhrase as $word) {
            if (($condition = $this->processSearchToken($word, $safeAttributes)) !== null) {
                $conditions[] = $condition;
            }
        }
        if (($condition = $this->processSearchRelated($query, $searchPhrase, $relationAttributes)) !== null) {
            $conditions[] = $condition;
        }
        if ($conditions !== ['or']) {
            $query->andWhere($conditions);
        }
        return $query;
    }

    /**
     * Use a distinct compare value for each column. Primary and foreign keys support multiple values.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function getAttributesSearchFilters(array $params, \yii\db\ActiveQuery $query)
    {
        if (!$this->load($params)) {
            return $query;
        }
        $this->validate();

        $tablePrefix = $this->getDb()->getSchema()->quoteSimpleTableName('t');
        $conditions = ['and'];
        $formats = $this->attributeFormats();
        $attributes = $this->attributes();
        $relations = $this->relations();
        $validAttributes = array_diff($attributes, array_keys($this->getErrors()));
        $attributeValues = $this->getAttributes($validAttributes);
        $hasILike = $this->getDb()->driverName === 'pgsql';
        /** @var EnumCollection $enums */
        $enums = Yii::$app->formatter instanceof Formatter ? Yii::$app->formatter->getEnums() : null;
        foreach ($validAttributes as $attribute) {
            $value = $attributeValues[$attribute];
            if (empty($value) || !isset($formats[$attribute])
                || ($enums !== null && !is_array($formats[$attribute]) && $enums->has($formats[$attribute]))
            ) {
                continue;
            }

            if (in_array($attribute, $relations)) {
                // only hasMany relations should be ever marked as valid attributes
                $conditions[] = $this->getRelationCondition($attribute, $value);
            } else {
                $conditions[] = $this->getAttributeCondition($attribute, $value, $formats, $tablePrefix, $hasILike);
            }
        }
        // don't clear attributes to allow rendering filled search form
        //$this->setAttributes(array_fill_keys($attributes, null));
        if ($conditions !== ['or']) {
            $query->andWhere($conditions);
        }
        return $query;
    }

    /**
     * If the 'ids' param is set, extracts primary keys from it and adds them as a query condition.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function getKeysSearchFilters(array $params, \yii\db\ActiveQuery $query)
    {
        if (!isset($params['ids'])) {
            return $query;
        }
        $keys = Action::importKey(self::primaryKey(), Action::explodeKeys($params['ids']));
        if (empty($keys)) {
            return $query->andWhere('1=0');
        }
        $prefixer = function ($key) {
            return 't.' . $key;
        };
        $keys = array_map(function ($key) use ($prefixer) {
            return array_combine(array_map($prefixer, array_keys($key)), array_values($key));
        }, $keys);

        return $query->andWhere(['in', array_map($prefixer, self::primaryKey()), $keys]);
    }

    /**
     * Parse $params data and build filters.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function getSearchFilters(array $params, \yii\db\ActiveQuery $query)
    {
        // set from with an alias
        if (empty($query->from)) {
            /* @var $modelClass ActiveRecord */
            $modelClass = $query->modelClass;
            $tableName = $modelClass::tableName();
            $query->from = [$tableName.' t'];
        }
        if ($query instanceof ActiveQuery) {
            $this->getQuickSearchFilters($query);
        }
        $this->getAttributesSearchFilters($params, $query);
        $this->getKeysSearchFilters($params, $query);
        return $query;
    }
}
