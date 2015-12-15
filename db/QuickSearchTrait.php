<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use netis\crud\web\EnumCollection;
use netis\crud\web\Formatter;
use Yii;

trait QuickSearchTrait
{
    use AttributeSearchTrait;

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
        $formatter = Yii::$app->formatter;
        /** @var EnumCollection $enums */
        $enums = $formatter instanceof Formatter ? $formatter->getEnums() : null;
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
            'netis.crud.ActiveRecord'
        );
        $attributeValues = $this->getAttributes($validAttributes);
        foreach ($validAttributes as $attribute) {
            $value = $attributeValues[$attribute];
            if (empty($value) || !isset($formats[$attribute])
                || ($enums !== null && !is_array($formats[$attribute]) && $enums->has($formats[$attribute]))
            ) {
                continue;
            }

            $conditions[] = $this->getAttributeCondition($attribute, $value, $formats, $tablePrefix, $this->getDb());
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
     *                                       'model' => netis\crud\db\ActiveRecord,
     *                                       'searchModel' => netis\crud\db\ActiveSearchTrait,
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
            $query->joinWith([$relationName => function ($query) use ($relationName) {
                /** @var \yii\db\ActiveQuery $query */
                /** @var \yii\db\ActiveRecord $class */
                $class = $query->modelClass;
                return $query->select(false)->from([$relationName => $class::tableName()]);
            }]);
            $query->distinct = true;
            $conditions = ['and'];
            /** @var ActiveSearchInterface $searchModel */
            $searchModel = $relation['searchModel'];
            if (!$searchModel instanceof ActiveSearchInterface) {
                continue;
            }
            foreach ($tokens as $token) {
                $condition = $searchModel->processSearchToken($token, $relation['attributes'], $relationName);
                if ($condition !== null) {
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
     * Gets the AR model and search model for specified relation.
     * @param \yii\db\ActiveQuery $activeRelation
     * @param string $scenario
     * @return array contains: model, searchModel and empty attributes array
     */
    protected function getQuickSearchRelation($activeRelation, $scenario)
    {
        /** @var ActiveRecord $relationModel */
        $relationModel = new $activeRelation->modelClass();
        $relationModel->scenario = $scenario;

        $parts = explode('\\', $activeRelation->modelClass);
        $modelClass = array_pop($parts);
        $namespace = implode('\\', $parts);
        $searchModelClass = $namespace . '\\search\\' . $modelClass;
        $relationSearchModel = class_exists($searchModelClass) ? new $searchModelClass : new $activeRelation->modelClass;

        return [
            'model'      => $relationModel,
            'searchModel'=> $relationSearchModel,
            'attributes' => [],
        ];
    }

    /**
     * Returns safe attributes (except foreign keys) and relation names.
     * @return array two arrays: safe and relation attribute names
     */
    protected function getQuickSearchAttributes()
    {
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
                $attribute = null;
            } else {
                $relationName = substr($attribute, 0, $pos);
                $attribute = substr($attribute, $pos + 1);
            }

            if (!isset($relationAttributes[$relationName])) {
                $relationAttributes[$relationName] = $this->getQuickSearchRelation($this->getRelation($relationName), $this->scenario);
            }
            if ($attribute === null) {
                /** @var \yii\db\ActiveRecord $relationModel */
                $relationModel = $relationAttributes[$relationName]['model'];
                /** @var LabelsBehavior $labelsBehavior */
                $labelsBehavior = $relationModel->getBehavior('labels');
                foreach ($labelsBehavior->attributes as $rcAttribute) {
                    $relationAttributes[$relationName]['attributes'][] = $rcAttribute;
                }
            } else {
                $relationAttributes[$relationName]['attributes'][] = $attribute;
            }
        }
        return [$safeAttributes, $relationAttributes];
    }

    /**
     * Use one value to compare against all columns.
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function addQuickSearchConditions(\yii\db\ActiveQuery $query)
    {
        if (!$query instanceof ActiveQuery) {
            return $query;
        }
        $searchPhrase = array_filter(array_map('trim', explode(ActiveSearchInterface::TOKEN_SEPARATOR, $query->quickSearchPhrase)));
        if (count($searchPhrase) === 2 && (string)intval($searchPhrase[0]) === $searchPhrase[0]
            && (string)intval($searchPhrase[1]) === $searchPhrase[1]
        ) {
            // special case, whole term is just one digit with decimal separator
            $searchPhrase = [trim($query->quickSearchPhrase)];
        }
        list ($safeAttributes, $relationAttributes) = $this->getQuickSearchAttributes();
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
}
