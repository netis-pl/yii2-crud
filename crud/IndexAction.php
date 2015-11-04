<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveQuery;
use netis\utils\db\ActiveSearchInterface;
use netis\utils\db\LabelsBehavior;
use netis\utils\web\Response;
use netis\utils\widgets\FormBuilder;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveField;

class IndexAction extends Action
{
    const SEARCH_QUICK_SEARCH = 1;
    const SEARCH_COLUMN_HEADERS = 2;
    const SEARCH_ADVANCED_FORM = 4;

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
            // additional authorization conditions are added in getQuery() method
            // using the "authorized" query
            call_user_func($this->checkAccess, 'read');
        }
        $model = new $this->modelClass();
        $searchModel = $this->getSearchModel();
        if ($this->prepareDataProvider !== null) {
            $dataProvider = call_user_func($this->prepareDataProvider, $this);
        } else {
            $dataProvider = $this->prepareDataProvider($searchModel);
        }

        $searchFields = FormBuilder::getFormFields($searchModel, array_merge(
            $this->getFields($searchModel, 'searchForm'),
            $this->getExtraFields($searchModel, 'searchForm')
        ), true);
        $columns = $this->getIndexGridColumns($model, $this->getFields($model, 'grid'));

        return [
            'dataProvider' => $dataProvider,
            'columns' => $columns,
            'buttons' => $this->getDefaultGridButtons($dataProvider),
            'searchModel' => $searchModel,
            'searchFields' => $searchFields,
        ];
    }

    public function addColumnFilters($columns, $searchFields)
    {
        $filterableColumns = [];
        foreach ($columns as $key => $column) {
            if (!is_array($column) || !isset($column['attribute'])) {
                continue;
            }
            $filterableColumns[$column['attribute']] = $key;
        }
        foreach ($searchFields as $fieldName => $searchField) {
            if (!$searchField instanceof ActiveField || !isset($filterableColumns[$fieldName])
                || !isset($searchField->parts['{input}'])
            ) {
                continue;
            }
            $key = $filterableColumns[$fieldName];
            $filter = $searchField->parts['{input}'];
            if (is_array($filter)) {
                $class = $filter['class'];
                $filter['clientOptions']['width'] = '15em';
                $columns[$key]['filter'] = $class::widget($filter);
            } else {
                $columns[$key]['filter'] = $filter;
            }
        }
        return $columns;
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
