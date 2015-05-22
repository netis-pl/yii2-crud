<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\data\Sort;
use yii\db\ActiveQuery;
use yii\db\Query;

trait ActiveSearchTrait
{
    /**
     * Returns a translated label for the model or specific operation.
     * @param string $operation one of: create, read, update, delete
     * @return string
     */
    abstract public function getCrudLabel($operation = null);

    /**
     * Returns columns for default index view.
     * @return array
     */
    public function getColumns()
    {
        $columns = [];

        foreach ($this->attributes() as $attribute) {
            $columns[] = $attribute;
        }

        return array_merge([
            ['class' => 'yii\grid\SerialColumn'],
        ], $columns, [
            ['class' => 'yii\grid\ActionColumn'],
        ]);
    }

    public function getDetailAttributes()
    {
        return $this->attributes();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @param Query $query
     * @param array $columns
     * @param Sort|array $sort
     * @param Pagination|array $pagination
     * @return ActiveDataProvider
     */
    public function search($params, Query $query = null, array $columns = null, $sort = null, $pagination = null)
    {
        if ($query === null) {
            /** @var ActiveQuery $query */
            $query = self::find();
        }
        if ($query instanceof ActiveQuery) {
            $query->defaultOrder();
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => $sort,
            'pagination' => $pagination,
        ]);

        if ($columns === null && (($string = $this->getBehavior('string')) !== null)) {
            $columns = $string->attributes;
        }

        $this->getRelationsSearchFilters($params, $query);

        $this->getSearchFilters($params, $query, $columns);

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

        return $dataProvider;
    }
}
