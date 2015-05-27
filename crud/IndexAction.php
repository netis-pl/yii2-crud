<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveQuery;
use netis\utils\db\ActiveSearchTrait;
use Yii;
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

        return [
            'dataProvider' => $this->prepareDataProvider(),
            'columns' => $this->getGridColumns(),
        ];
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @return array grid columns
     */
    protected function getGridColumns()
    {
        if (!$this->controller instanceof ActiveController) {
            /** @var \yii\db\BaseActiveRecord $model */
            $model = new $this->modelClass;

            return $model->attributes();
        }

        /** @var ActiveRecord $model */
        $model = new $this->controller->modelClass();
        list($behaviorAttributes, $blameableAttributes) = $this->getModelBehaviorAttributes($model);
        return self::formatGridColumns($model, $behaviorAttributes, $blameableAttributes);
    }
    /**
     * 
     * @param Object $model
     * @param array $behaviorAttributes
     * @param array $blameableAttributes
     * @return array grid columns
     */
    public static function formatGridColumns($model, $behaviorAttributes, $blameableAttributes)
    {
        $formats = $model->attributeFormats();
        $columns = [];
        $keys    = self::getModelKeys($model);
        foreach ($model->attributes() as $attribute) {
            if (in_array($attribute, $keys) || in_array($attribute, $behaviorAttributes)) {
                continue;
            }
            $columns[] = $attribute . ':' . $formats[$attribute];
        }
        foreach ($model->relations() as $relation) {
            $activeRelation = $model->getRelation($relation);
            if ($activeRelation->multiple) {
                continue;
            }
            foreach ($activeRelation->link as $left => $right) {
                if (in_array($left, $blameableAttributes)) {
                    continue;
                }
            }

            if (!Yii::$app->user->can($activeRelation->modelClass . '.read')) {
                continue;
            }
            $columns[] = [
                'attribute' => $relation,
                'format'    => 'crudLink',
                'visible'   => true,
            ];
        }

        $actionColumn = new ActionColumn();
        return array_merge([
            [
                'class'         => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
            /* 'buttons' => [
              'view'   => function ($url, $model, $key) use ($actionColumn) {
              if (!Yii::$app->user->can($model::className().'.read')) {
              return null;
              }
              return $actionColumn->buttons['view'];
              },
              'update' => function ($url, $model, $key) use ($actionColumn) {
              if (!Yii::$app->user->can($model::className().'.update')) {
              return null;
              }
              return $actionColumn->buttons['update'];
              },
              'delete' => function ($url, $model, $key) use ($actionColumn) {
              if (!Yii::$app->user->can($model::className().'.delete')) {
              return null;
              }
              return $actionColumn->buttons['delete'];
              },
              ], */
            ],
            [
                'class'         => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ],
                ], $columns);
    }

    /**
     * Prepares the data provider that should return the requested collection of the models.
     * @return ActiveDataProvider
     */
    protected function prepareDataProvider()
    {
        if ($this->prepareDataProvider !== null) {
            return call_user_func($this->prepareDataProvider, $this);
        }

        if ($this->controller instanceof ActiveController && $this->controller->searchModelClass !== null) {
            /** @var ActiveSearchTrait $searchModel */
            $searchModel = new $this->controller->searchModelClass();
            return $searchModel->search(Yii::$app->request->queryParams);
        }
        /** @var \yii\db\BaseActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        /** @var ActiveQuery $query */
        $query = $modelClass::find();
        if ($query instanceof ActiveQuery) {
            $query->defaultOrder();
        }

        return new ActiveDataProvider(['query' => $query]);
    }
}
