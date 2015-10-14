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

trait ActiveSearchTrait
{
    use QuickSearchTrait;

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
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

        // add extra authorization conditions
        $query->authorized($this, $this->getCheckedRelations(), Yii::$app->user->getIdentity());

        if ($query instanceof ActiveQuery) {
            if (isset($params['query']) && !isset($params['ids'])) {
                $query->setActiveQueries($params['query']);
            }
            if (isset($params['search'])) {
                $query->quickSearchPhrase = $params['search'];
            }
        }

        $this->addConditions($params, $query);

        if (is_array($sort)) {
            $sort = array_merge($this->getSort($query), $sort);
        }
        if (is_array($pagination)) {
            $pagination = array_merge([
                'pageSizeLimit' => [-1, 0x7FFFFFFF],
                'defaultPageSize' => 25,
            ], $pagination);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => $sort,
            'pagination' => $pagination,
        ]);

        return $dataProvider;
    }

    /**
     * Parse $params data and build filters.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function addConditions(array $params, \yii\db\ActiveQuery $query)
    {
        // set from with an alias
        if (empty($query->from)) {
            /* @var $modelClass ActiveRecord */
            $modelClass = $query->modelClass;
            $tableName = $modelClass::tableName();
            $query->from = [$tableName.' t'];
        }
        if ($query instanceof ActiveQuery) {
            $this->addQuickSearchConditions($query);
        }
        if (!$this->load($params) && $this->validate()) {
            $this->addAttributesSearchConditions($query);
        }
        if (isset($params['ids'])) {
            $keys = Action::importKey(self::primaryKey(), Action::explodeKeys($params['ids']));
            $this->addKeysSearchConditions($keys, $query);
        }
        return $query;
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
     */
    public function formName()
    {
        return 'search';
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
     * Use a distinct compare value for each column. Primary and foreign keys support multiple values.
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function addAttributesSearchConditions(\yii\db\ActiveQuery $query)
    {
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
     * @param array $keys
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function addKeysSearchConditions($keys, \yii\db\ActiveQuery $query)
    {
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
}
