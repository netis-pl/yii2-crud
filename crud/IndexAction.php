<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveQuery;
use netis\utils\db\LabelsBehavior;
use netis\utils\web\Response;
use netis\utils\widgets\FormBuilder;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\helpers\Html;
use yii\helpers\Url;

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
     * @var bool should a serial column be used
     */
    public $useSerialColumn = true;
    /**
     * @var bool should a checkbox column be used
     */
    public $useCheckboxColumn = false;


    /**
     * @return ActiveDataProvider
     */
    public function run()
    {
        if ($this->checkAccess) {
            // additional authorization conditions are added in prepareDataProvider() method
            // using the "authorized" query
            call_user_func($this->checkAccess, 'read');
        }
        $controller = $this->controller;
        $model = new $this->modelClass();
        $searchModel = $controller instanceof ActiveController ? $controller->getSearchModel() : $model;
        // prepared here because it modifies $model
        $dataProvider = $this->prepareDataProvider($searchModel);

        return [
            'dataProvider' => $dataProvider,
            'columns' => $this->getIndexGridColumns($model, $this->getFields($model, 'grid')),
            'buttons' => $this->getDefaultGridButtons($dataProvider),
            'searchModel' => $searchModel,
            'searchFields' => FormBuilder::getFormFields($searchModel, array_merge(
                $this->getFields($searchModel, 'searchForm'),
                $this->getExtraFields($searchModel, 'searchForm')
            ), true),
        ];
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @return array grid columns
     */
    public function getIndexGridColumns($model, $fields)
    {
        /** @var ActiveController $controller */
        $controller = $this->controller;
        $actionColumn = new ActionColumn();
        $actionColumn->buttonOptions['class'] = 'operation-button';
        $extraColumns = [
            [
                'class'         => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
                'controller'    => Yii::$app->crudModelsMap[$model::className()],
                'template'      => '{update} {delete} {toggle}', // {view} removed because representing column is linked
                'buttons' => [
                    'view'   => function ($url, $model, $key) use ($controller, $actionColumn) {
                        if (!$controller->hasAccess('read', $model)) {
                            return null;
                        }

                        return $actionColumn->buttons['view']($url, $model, $key);
                    },
                    'update' => function ($url, $model, $key) use ($controller, $actionColumn) {
                        if (!$controller->hasAccess('update', $model)) {
                            return null;
                        }

                        return $actionColumn->buttons['update']($url, $model, $key);
                    },
                    'delete' => function ($url, $model, $key) use ($controller, $actionColumn) {
                        if (!$controller->hasAccess('delete', $model)) {
                            return null;
                        }

                        return $actionColumn->buttons['delete']($url, $model, $key);
                    },
                    'toggle' => function ($url, $model, $key) use ($controller, $actionColumn) {
                        /** @var \yii\db\ActiveRecord $model */
                        if ($model->getBehavior('toggable') === null || !$controller->hasAccess('delete', $model)) {
                            return null;
                        }

                        $enabled = $model->isEnabled();
                        $icon    = '<span class="glyphicon glyphicon-'.($enabled ? 'ban' : 'reply').'"></span>';
                        $options = array_merge([
                            'title'       => $enabled ? Yii::t('app', 'Disable') : Yii::t('app', 'Enable'),
                            'aria-label'  => $enabled ? Yii::t('app', 'Disable') : Yii::t('app', 'Enable'),
                            'data-pjax'   => '0',
                        ], $enabled ? [
                            'data-confirm' => Yii::t('app', 'Are you sure you want to disable this item?'),
                        ] : [], $actionColumn->buttonOptions);
                        return \yii\helpers\Html::a($icon, $url, $options);
                    },
                ],
            ],
        ];
        if ($this->useSerialColumn) {
            $extraColumns[] = [
                'class'         => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ];
        }
        if ($this->useCheckboxColumn) {
            //$classParts = explode('\\', $this->modelClass);
            $extraColumns[] = [
                'class'         => 'yii\grid\CheckboxColumn',
                'headerOptions' => ['class' => 'column-checkbox'],
                'multiple'      => true,
                //'name'          => end($classParts).'[]',
            ];
        }

        return array_merge($extraColumns, static::getGridColumns($model, $fields));
    }

    /**
     * @inheritdoc
     */
    protected static function getAttributeColumn($model, $field, $format)
    {
        /** @var LabelsBehavior $behavior */
        $behavior = $model->getBehavior('labels');
        if (in_array($field, $behavior->attributes)) {
            return array_merge(
                parent::getAttributeColumn($model, $field, ['crudLink', [], 'view', function ($value) use ($field) {
                    return Html::encode($value->$field);
                }]),
                [
                    'value' => function ($model, $key, $index, $column) {
                        return $model;
                    },
                ]
            );
        }
        return parent::getAttributeColumn($model, $field, $format);
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
            'pageSizeLimit' => [-1, 0x7FFFFFFF],
            'defaultPageSize' => 25,
        ];

        $params = Yii::$app->request->queryParams;
        if ($model instanceof \netis\utils\crud\ActiveRecord) {
            // add extra authorization conditions
            $query->authorized($model, $model->getCheckedRelations(), Yii::$app->user->getIdentity());

            if (isset($params['query']) && !isset($params['ids']) && $query instanceof \netis\utils\db\ActiveQuery) {
                $availableQueries = $query->publicQueries();
                if (!is_array($params['query'])) {
                    $params['query'] = explode(',', $params['query']);
                }
                foreach ($params['query'] as $namedQuery) {
                    if (($namedQuery = trim($namedQuery)) === '' || !in_array($namedQuery, $availableQueries)) {
                        continue;
                    }
                    call_user_func([$query, $namedQuery]);
                }
            }
            return $model->search($params, $query, $sort, $pagination);
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

    /**
     * Creates default buttons to perform actions on grid items.
     * @param \yii\data\BaseDataProvider $dataProvider
     * @return array each button is an array of keys: icon, label, url, options
     */
    protected function getDefaultGridButtons($dataProvider)
    {
        /** @var \yii\filters\ContentNegotiator $negotiator */
        $negotiator = $this->controller->getBehavior('contentNegotiator');
        return [
            [
                'icon' => 'glyphicon glyphicon-file',
                'label' => 'XLS',
                'url' => Url::current([
                    $negotiator->formatParam                 => Response::FORMAT_XLS,
                    $dataProvider->pagination->pageSizeParam => -1,
                ]),
                'options' => ['class' => 'btn btn-default', 'data-pjax' => 0],
            ],
            [
                'icon' => 'glyphicon glyphicon-file',
                'label' => 'CSV',
                'url' => Url::current([
                    $negotiator->formatParam                 => Response::FORMAT_CSV,
                    $dataProvider->pagination->pageSizeParam => -1,
                ]),
                'options' => ['class' => 'btn btn-default', 'data-pjax' => 0],
            ],
        ];
    }
}
