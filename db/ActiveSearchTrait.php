<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

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
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        /** @var ActiveQuery $query */
        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere($this->getAttributes());
        /*foreach ($this->attributes() as $attribute) {
            $query->orFilterWhere($this->$attribute);
            $query->andFilterWhere(['like', 'symbol', $this->symbol])
                ->andFilterWhere(['like', 'name', $this->name]);
        }*/

        return $dataProvider;
    }
}
