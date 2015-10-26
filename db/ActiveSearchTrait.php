<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use Yii;
use netis\utils\crud\ActiveRecord;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;

trait ActiveSearchTrait
{
    use QuickSearchTrait;

    /**
     * Creates data provider instance with search query applied
     *
     * @param \yii\db\ActiveQuery $query
     * @param Sort|array $sort
     * @param Pagination|array $pagination
     * @return ActiveDataProvider
     */
    public function search(\yii\db\ActiveQuery $query = null, $sort = [], $pagination = [])
    {
        if ($query === null) {
            /** @var ActiveQuery $query */
            $query = self::find();
        }

        if (is_array($sort)) {
            $sort = array_merge($this->getSortConfig($query, $this->attributes()), $sort);
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
     * Build filters based on this model attributes and other query options.
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    public function addConditions(\yii\db\ActiveQuery $query)
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
        $this->validate();
        $this->addAttributesSearchConditions($query);
        return $query;
    }

    /**
     * Creates a Sort object configuration using query default order.
     * @param \yii\db\ActiveQuery $query
     * @param array $attributes
     * @return array
     */
    public function getSortConfig(\yii\db\ActiveQuery $query, array $attributes)
    {
        $defaults = $query instanceof ActiveQuery ? $query->getDefaultOrderColumns() : [];
        $sort = [
            'enableMultiSort' => true,
            'attributes' => [],
            'defaultOrder' => $defaults,
        ];

        foreach ($attributes as $attribute) {
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
}
