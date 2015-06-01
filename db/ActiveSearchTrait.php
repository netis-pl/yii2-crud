<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

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
     * @param array $columns
     * @param Sort|array $sort
     * @param Pagination|array $pagination
     * @return ActiveDataProvider
     */
    public function search($params, \yii\db\ActiveQuery $query = null, array $columns = null, $sort = [], $pagination = [])
    {
        if ($query === null) {
            /** @var ActiveQuery $query */
            $query = self::find();
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => is_array($sort) ? array_merge($this->getSort($query), $sort) : $sort,
            'pagination' => $pagination,
        ]);

        if ($columns === null && (($string = $this->getBehavior('string')) !== null)) {
            $columns = $string->attributes;
        }

        $this->getRelationsSearchFilters($params, $query);

        $this->getSearchFilters($params, $query, $columns);

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
     * Assigns specified token to specified attributes and validates
     * current model to filter the values. Then, creates search condition.
     * @param  \yii\db\ActiveQuery $query
     * @param  string $token     one search token extracted from whole term
     * @param  array $attributes attributes safe to search
     * @param  string $tablePrefix
     * @return \yii\db\ActiveQuery all conditions joined with OR operator, should be merged with main criteria object
     */
    protected function processSearchToken(\yii\db\ActiveQuery $query, $token, array $attributes, $tablePrefix = null)
    {
        /** @var \yii\db\Schema $schema */
        $schema = $this->getDb()->getSchema();

        /*$tablePrefix = $schema->quoteSimpleTableName($tablePrefix === null ? 't' : $tablePrefix);
        $criteria = new ActiveQuery();
        $uiTypes = $this->uiTypes();
        YII_DEBUG && Yii::trace(
            'Processing token ' . $token . ', safe attributes: ' . print_r($attributes, true),
            'application.model.NetActiveRecord'
        );
        // to support searching in enums token must be first translated to matching values
        $plainAttributes = [];
        foreach ($attributes as $attribute) {
            if (!isset($uiTypes[$attribute]) || !is_array($uiTypes[$attribute])
                || $uiTypes[$attribute]['type'] != 'set'
            ) {
                $plainAttributes[$attribute] = $token;
                continue;
            }

            $enumAttributes[$attribute] = $token;
            // do a linear search in map values and then use matching in query criteria
            $matching = [];
            foreach ($uiTypes[$attribute]['map'] as $key => $label) {
                if (mb_stripos($label, $token, 0, 'UTF-8')!==false) {
                    $matching[] = $key;
                }
            }
            if (!empty($matching)) {
                // don't validate, because rules only allow one value anyway
                $criteria->addInCondition($tablePrefix.'.'.$schema->quoteSimpleColumnName($attribute), $matching, 'OR');
            }
        }
        $this->setAttributes($plainAttributes);
        $this->validate($attributes);
        $validAttributes = array_diff($attributes, array_keys($this->getErrors()));
        YII_DEBUG && Yii::trace(
            'Processing token ' . $token . ', validated in: ' . print_r($validAttributes, true),
            'application.model.NetActiveRecord'
        );
        $attributeValues = $this->getAttributes($validAttributes);
        foreach ($validAttributes as $attribute) {
            $value = $attributeValues[$attribute];
            if ($value === null || !isset($uiTypes[$attribute]) || is_array($uiTypes[$attribute])) {
                continue;
            }

            switch ($uiTypes[$attribute]) {
                default:
                    $criteria->compare(
                        $tablePrefix . '.' . $schema->quoteSimpleColumnName($attribute),
                        $value,
                        false,
                        'OR'
                    );
                    break;
                case 'text':
                case 'longtext':
                    $criteria->addSearchCondition(
                        $tablePrefix . '.' . $schema->quoteSimpleColumnName($attribute),
                        $value,
                        true,
                        'OR',
                        $db->driverName == 'pgsql' ? 'ILIKE' : 'LIKE'
                    );
                    break;
                case 'json':
                    $column = $tablePrefix . '.' . $schema->quoteSimpleColumnName($attribute);
                    $param  = CDbCriteria::PARAM_PREFIX . CDbCriteria::$paramCount++;
                    $criteria->addCondition('EXISTS(SELECT 1 FROM json_array_elements(' . $column . ') a WHERE a::text '
                        . ($db->driverName == 'pgsql' ? 'ILIKE' : 'LIKE') . " '%' || " . $param . " || '%')", 'OR');
                    $criteria->params[$param] = $value; //strtr($value, array('%'=>'\%', '_'=>'\_', '\\'=>'\\\\'));
                    break;
            }
        }
        $this->unsetAttributes($attributes);

        return $criteria;*/
    }

    /**
     * Adds a condition to search in relations using subquery.
     * @todo this should be called for each token, to group their conditions with OR and group token groups with AND
     *
     * @param \yii\db\ActiveQuery $query
     * @param  array $tokens             all search tokens extracted from term
     * @param  array $relationAttributes array of string(relation name) => array(
     *                                       'model'=>NetActiveRecord,
     *                                       'attributes'=>array
     *                                   )
     * @return \yii\db\ActiveQuery $query
     */
    protected function processSearchRelated(\yii\db\ActiveQuery $query, array $tokens, array $relationAttributes)
    {
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
            foreach ($tokens as $token) {
                if (trim($token)=='') {
                    continue;
                }
                //$relation['model']->processSearchToken($token, $relation['attributes'], $relationName);
            }
        }

        return $query;
    }

    /**
     * While fetching data for MANY_MANY, we get all records from the other model and left join selected rows only.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function getRelationsSearchFilters(array $params, \yii\db\ActiveQuery $query)
    {
        if (!isset($params['relatedWith']) || !isset($params['relatedKey'])) {
            return $query;
        }
        /*$relations  = $this->relations();

        if (!isset($relations[$params['relatedWith']])) {
            throw new Exception(Yii::t('app', 'Unknown relation {relation}', [
                '{relation}' => $params['relatedWith'],
            ]));
        }
        $with = $query->with === null ? [] : $query->with;
        / **
         * @todo validate indexes?
         * /
        $relatedModelName = $relations[$params['relatedWith']][1];
        $relatedModel = NetActiveRecord::model($relatedModelName);
        $relatedKey = $relations[$params['relatedWith']][2];
        / **
         * @todo why this was disabled? apparently it breaks for HAS_MANY relation,
         *       where relatedWith is practically filtered out by the 'on' condition
         * /
        $relatedKeyValue = (string) $params['relatedKey'];
        $pks = $relatedModel->getTableSchema()->primaryKey;
        $schema = $this->getDbConnection()->getSchema();
        if ($relations[$params['relatedWith']][0] == self::HAS_ONE) {
            $relatedKey = $pks[$pks[0] == $relatedKey ? 1 : 0];
            $with[$params['relatedWith']] = [
                'params' => [':'.$relatedKey => isset($relatedKeyValue{0}) ? $relatedKeyValue : null],
            ];
        } else {
            $relatedKey = $relations[$params['relatedWith']][2];
            $with[$params['relatedWith']] = [
                'on' => $schema->quoteSimpleTableName($params['relatedWith'])
                    . '.' . $schema->quoteSimpleColumnName($pks).'=:'.$pks,
                'params' => [':'.$pks => isset($relatedKeyValue{0}) ? $relatedKeyValue : null],
            ];
        }
        if (isset($request['relatedOnly']) && $request['relatedOnly']=='true') {
            if (isset($request['ajax']) && isset($request[$request['ajax'].'-selected'])
                && trim($request[$request['ajax'] . '-selected']) != ''
            ) {
                $relatedOnlyCriteria = new CDbCriteria;
                $relatedOnlyCriteria->addInCondition(
                    $schema->quoteSimpleTableName('t') . '.' . $pks,
                    array_map('intval', explode(',', $request[$request['ajax'] . '-selected']))
                );
                $relatedOnlyCriteria->addCondition(
                    $schema->quoteSimpleTableName($request['relatedWith']) . '.'
                    . $schema->quoteSimpleColumnName($relatedKey) . ' IS NOT NULL',
                    'OR'
                );
                $query->mergeWith($relatedOnlyCriteria);
            } else {
                $with[$request['relatedWith']]['joinType'] = 'INNER JOIN';
                $with[$request['relatedWith']]['together'] = true;
            }
        }
        $query->with = $with;
        return $query;*/
    }

    /**
     * Use one value to compare against all columns.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @param array $columns
     * @return \yii\db\ActiveQuery
     */
    protected function getQuickSearchFilters(array $params, \yii\db\ActiveQuery $query, array $columns = null)
    {
        if (!isset($params['search']) || empty($params['search'])) {
            return $query;
        }
        $searchPhrase = array_map('trim', explode(',', trim($params['search'])));
        if (count($searchPhrase) == 2 && (string)intval($searchPhrase[0]) === $searchPhrase[0]
            && (string)intval($searchPhrase[1]) === $searchPhrase[1]
        ) {
            // special case, whole term is just one digit with decimal separator
            $searchPhrase = [trim($params['search'])];
        }
        // skip foreign keys, relations are search in other way
        $allAttributes = array_diff(
            $this->safeAttributes(),
            array_keys($this->getTableSchema()->foreignKeys)
        );
        $safeAttributes = [];
        $relationAttributes = [];

        foreach ($allAttributes as $attribute) {
            if (($pos = strpos($attribute, '.')) === false) {
                $safeAttributes[] = $attribute;
                continue;
            }
            $relation = substr($attribute, 0, $pos);
            $relationMethod = 'get'.$relation;
            $relationModel = (new $this->$relationMethod())->modelClass;
            $relationModel->scenario = $this->scenario;
            $attribute = substr($attribute, $pos + 1);
            if (!isset($relationAttributes[$relation])) {
                $relationAttributes[$relation] = [
                    'model'      => $relationModel,
                    'attributes' => [],
                ];
            }
            $relationAttributes[$relation]['attributes'][] = $attribute;
        }
        foreach ($searchPhrase as $word) {
            if (trim($word) === '') {
                continue;
            }
            $this->processSearchToken($query, $word, $safeAttributes);
        }
        $this->processSearchRelated($query, $searchPhrase, $relationAttributes);
        return $query;
    }

    /**
     * Use a distinct compare value for each column.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @param array $columns
     * @return \yii\db\ActiveQuery
     */
    protected function getAttributesSearchFilters(array $params, \yii\db\ActiveQuery $query, array $columns = null)
    {
        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
        } else {
            $query->andFilterWhere($this->getAttributes());
            /*foreach ($this->attributes() as $attribute) {
                $query->orFilterWhere($this->$attribute);
                $query->andFilterWhere(['like', 'symbol', $this->symbol])
                    ->andFilterWhere(['like', 'name', $this->name]);
            }*/
        }
        return $query;
    }

    /**
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @param array $columns
     * @return \yii\db\ActiveQuery
     */
    protected function getAdvancedSearchFilters(array $params, \yii\db\ActiveQuery $query, array $columns = null)
    {
        if (!isset($params['advfilter']) || !is_array($params['advfilter'])) {
            return $query;
        }
        /** @var \yii\db\Schema $schema */
        $schema = $this->getDb()->getSchema();
        $t = $schema->quoteSimpleTableName('t');
        $counter = 0;
        foreach ($params['advfilter'] as $key => $value) {
            if (is_array($value)) {
                // attributes of a related model
                /**
                 * @todo what about MANY_MANY relations? we just have to use a pivot table
                 * - another join but we got all the data (key values)
                 */
                $condition = [];
                foreach ($value as $rkey => $rvalue) {
                    if (empty($rvalue)) {
                        continue;
                    }
                    $condition[] = "$key.$rkey IN ($rvalue)";
                }
                if (!is_array($query->with)) {
                    $query->with = [];
                }
                $query->joinWith([$key => function ($query) use ($condition) {
                    /** @var Query $query */
                    return $query->select(false)->where($condition);
                }], true, 'INNER JOIN');
                $query->distinct = true;
            } else {
                if (empty($value)) {
                    continue;
                }
                $query->andWhere("$t.$key = :advfilter".$counter, [':advfilter'.$counter++ => $value]);
            }
        }
        return $query;
    }

    /**
     * Parse $params data and build filters.
     * @param array $params
     * @param \yii\db\ActiveQuery $query
     * @param array $columns
     * @return \yii\db\ActiveQuery
     */
    protected function getSearchFilters(array $params, \yii\db\ActiveQuery $query, array $columns = null)
    {
        $this->getQuickSearchFilters($params, $query, $columns);
        $this->getAttributesSearchFilters($params, $query, $columns);
        $this->getAdvancedSearchFilters($params, $query, $columns);
        return $query;
    }
}
