<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveQuery;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;

class IndexAction extends Action
{
    /**
     * @var callable a PHP callable that will be called to prepare a data provider that
     * should return a collection of the models. If not set, [[prepareDataProvider()]] will be used instead.
     * The signature of the callable should be:
     *
     * ```php
     * function ($action) {
     *     // $action is the action object currently running
     * }
     * ```
     *
     * The callable should return an instance of [[ActiveDataProvider]].
     */
    public $prepareDataProvider;


    /**
     * @return ActiveDataProvider
     */
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }
        $controller = $this->controller;
        $model = $controller instanceof ActiveController ? $controller->getSearchModel() : new $this->modelClass();
        // prepared here because it modifies $model
        $dataProvider = $this->prepareDataProvider($model);
        $fields = $this->getFields($model);

        return [
            'dataProvider' => $dataProvider,
            'columns' => static::getIndexGridColumns($model, $fields),
            'searchModel' => $model,
            'searchFields' => \netis\utils\widgets\FormBuilder::getFormFields($model, $fields, true),
        ];
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @return array grid columns
     */
    public static function getIndexGridColumns($model, $fields)
    {
        $actionColumn = new ActionColumn();
        return array_merge([
            [
                'class'         => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
                'controller'    => Yii::$app->crudModelsMap[$model::className()],
                'buttons' => [
                    'view'   => function ($url, $model, $key) use ($actionColumn) {
                        if (!Yii::$app->user->can($model::className() . '.read')) {
                            return null;
                        }

                        return $actionColumn->buttons['view']($url, $model, $key);
                    },
                    'update' => function ($url, $model, $key) use ($actionColumn) {
                        if (!Yii::$app->user->can($model::className() . '.update')) {
                            return null;
                        }

                        return $actionColumn->buttons['update']($url, $model, $key);
                    },
                    'delete' => function ($url, $model, $key) use ($actionColumn) {
                        if (!Yii::$app->user->can($model::className() . '.delete')) {
                            return null;
                        }

                        return $actionColumn->buttons['delete']($url, $model, $key);
                    },
                ],
            ],
            [
                'class'         => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ],
        ], static::getGridColumns($model, $fields));
    }

    /**
     * Prepares the data provider that should return the requested collection of the models.
     * @param \yii\base\Model
     * @return ActiveDataProvider
     */
    protected function prepareDataProvider($model)
    {
        if ($this->prepareDataProvider !== null) {
            return call_user_func($this->prepareDataProvider, $this);
        }

        /** @var ActiveQuery $query */
        $query = $model::find();
        $sort = $this->getSort($query);
        $pagination = [
            'pageSizeLimit' => [1, 0x7FFFFFFF],
            'defaultPageSize' => 25,
        ];

        if ($model instanceof \netis\utils\crud\ActiveRecord) {
            return $model->search(Yii::$app->request->queryParams, $query, $sort, $pagination);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => $sort,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Creates a Sort object configuration using query default order.
     * @param ActiveQuery $query
     * @return array
     */
    private function getSort($query)
    {
        /* @var $model \netis\utils\crud\ActiveRecord */
        $model = new $query->modelClass;
        $defaults = $query instanceof ActiveQuery ? $query->getDefaultOrderColumns() : [];
        $sort = [
            'enableMultiSort' => true,
            'attributes' => [],
            'defaultOrder' => $defaults,
        ];

        foreach ($model->attributes() as $attribute) {
            $sort['attributes'][$attribute] = [
                'asc' => array_merge([$attribute => SORT_ASC], $defaults),
                'desc' => array_merge([$attribute => SORT_DESC], $defaults),
            ];
        }
        return $sort;
    }
}
