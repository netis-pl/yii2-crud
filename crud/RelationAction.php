<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class RelationAction extends IndexAction
{
    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @return array grid columns
     */
    public static function getIndexGridColumns($model, $fields)
    {
        $id = Yii::$app->request->getQueryParam('id');
        $relation = Yii::$app->request->getQueryParam('relation');
        foreach ($fields as $key => $field) {
            if (((is_array($field) || is_callable($field)) && $key === $relation) || $field === $relation) {
                unset($fields[$key]);
            }
        }
        return array_merge([
            [
                'class'         => 'yii\grid\CheckboxColumn',
                'headerOptions' => ['class' => 'column-serial'],
                'checkboxOptions' => function ($model, $key, $index, $column) use ($id, $relation) {
                    /** @var \yii\db\ActiveRecord $model */
                    $options = [
                        'value' => is_array($key)
                            ? json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                            : $key,
                    ];
                    if ($model->$relation !== null && Action::exportKey($model->$relation->getPrimaryKey()) === $id) {
                        $options['checked'] = true;
                        $options['disabled'] = true;
                    }
                    return $options;
                },
            ],
            [
                'class'         => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ],
        ], self::getGridColumns($model, $fields));
    }

    /**
     * Prepares the data provider that should return the requested collection of the models.
     * @param \yii\base\Model
     * @return ActiveDataProvider
     */
    protected function prepareDataProvider($model)
    {
        $dataProvider = parent::prepareDataProvider($model);
        /** @var \yii\db\ActiveQuery $query */
        $query = $dataProvider->query;

        // lazy load related models to mark checkboxes
        $relation = Yii::$app->request->getQueryParam('relation');
        $query->with($relation);

        return $dataProvider;
    }
}
