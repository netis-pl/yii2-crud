<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;
use yii\db\TableSchema;

trait ActiveSearchTrait
{
    use QuickSearchTrait;

    /**
     * Creates data provider instance with search query applied. You can pass {@link Pagination} configuration in
     * $pagination param. If you want set it up globally then you should use DI like this:
     * ```
     * 'bootstrap' => [
     *     function () {
     *         \Yii::$container->set(\yii\data\Pagination::className(), [
     *             'pageSizeLimit' => [-1, 0x7FFFFFFF],
     *             'defaultPageSize' => 50,
     *         ]);
     *     },
     * ],
     * ```
     *
     * @param \yii\db\ActiveQuery $query
     * @param Sort|array $sort
     * @param Pagination|array $pagination
     *
     * @return ActiveDataProvider
     */
    public function search(\yii\db\ActiveQuery $query = null, $sort = [], $pagination = [])
    {
        if ($query === null) {
            /** @var ActiveQuery $query */
            $query = self::find();
        }

        if (is_array($sort)) {
            $defaultSort = $this->getSortConfig($query, $this->attributes());
            //if sort config has defaultOrder set then append defaultOrders at the end instead of beginning
            if (isset($sort['defaultOrder'])) {
                $sort['defaultOrder'] = array_merge($sort['defaultOrder'], $defaultSort['defaultOrder']);
                unset($defaultSort['defaultOrder']);
            }
            $sort = \yii\helpers\ArrayHelper::merge($defaultSort, $sort);
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
     * Warning! Main table in the query should have the 't' alias set.
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    public function addConditions(\yii\db\ActiveQuery $query)
    {
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

        /** @var TableSchema $tableSchema */
        $tableSchema = $this->getTableSchema();
        foreach ($attributes as $attribute) {
            if ($tableSchema->getColumn($attribute) === null) {
                continue;
            }
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
     * Restores marking relation attributes as safe when they've got validation rules.
     */
    public function safeAttributes()
    {
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

        return $attributes;
    }
}
