<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\widgets;

use netis\utils\crud\Action;
use netis\utils\db\ActiveQuery;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

class FormBuilder
{
    /**
     * Registers JS code to help initialize Select2 widgets
     * with access to netis\utils\crud\ActiveController API.
     * @param \yii\web\View $view
     */
    public static function registerSelect($view)
    {
        $script = <<<JavaScript
(function (s2helper, $, undefined) {
    "use strict";
    s2helper.formatResult = function (result, container, query, escapeMarkup, depth) {
        if (typeof depth == 'undefined') {
            depth = 0;
        }
        var markup = [];
        window.Select2.util.markMatch(result, query.term, markup, escapeMarkup);
        return markup.join("");
    };

    // generates query params
    s2helper.data = function (term, page) {
        return { search: term, page: page };
    };

    // builds query results from ajax response
    s2helper.results = function (data, page) {
        return { results: data.items, more: page < data._meta.pageCount };
    };

    s2helper.initSingle = function (element, callback) {
        $.getJSON(element.data('select2').opts.ajax.url, {ids: element.val()}, function (data) {
            if (typeof data.items[0] != 'undefined')
                callback(data.items[0]);
        });
    };

    s2helper.initMulti = function (element, callback) {
        $.getJSON(element.data('select2').opts.ajax.url, {ids: element.val()}, function (data) {callback(data.items);});
    };
}( window.s2helper = window.s2helper || {}, jQuery ));
JavaScript;
        $view->registerJs($script, \yii\web\View::POS_END);
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param array $dbColumns
     * @param \yii\db\ActiveQuery $activeRelation
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @return array
     */
    protected static function getHasOneRelationField($model, $relation, $dbColumns, $activeRelation, $multiple = false)
    {
        return static::getRelationWidget($model, $relation, $dbColumns, $activeRelation, $multiple);
    }

    /**
     * To enable this, override and return getRelationWidget().
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param array $dbColumns
     * @param \yii\db\ActiveQuery $activeRelation
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @return array
     */
    protected static function getHasManyRelationField($model, $relation, $dbColumns, $activeRelation, $multiple = false)
    {
        // disabled because it's not yet implemented in ActiveSearchTrait
        if (false && $multiple) {
            return static::getRelationWidget($model, $relation, $dbColumns, $activeRelation, $multiple);
        }
        return null;
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param array $dbColumns
     * @param \yii\db\ActiveQuery $activeRelation
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @return array
     */
    protected static function getRelationWidget($model, $relation, $dbColumns, $activeRelation, $multiple = false)
    {
        $isMany = $activeRelation->multiple;
        $foreignKeys = array_values($activeRelation->link);
        $foreignKey = reset($foreignKeys);
        $route = Yii::$app->crudModelsMap[$activeRelation->modelClass];
        /** @var \yii\db\ActiveRecord $relModel */
        $relModel = new $activeRelation->modelClass;
        $primaryKey = $relModel->getTableSchema()->primaryKey;
        if (($labelAttributes = $relModel->getBehavior('labels')->attributes) !== null) {
            $fields = array_merge($primaryKey, $labelAttributes);
            $labelField = reset($labelAttributes);
        } else {
            $fields = $primaryKey;
            $labelField = reset($primaryKey);
        }
        if ($isMany) {
            $value = Action::implodeEscaped(
                Action::KEYS_SEPARATOR,
                array_map(
                    '\netis\utils\crud\Action::exportKey',
                    $activeRelation->select($primaryKey)->asArray()->all()
                )
            );
        } else {
            $value = Action::exportKey($model->getAttributes($foreignKeys));
        }
        $items = null;
        if ($route === null) {
            $relQuery = $relModel::find();
            if ($relQuery instanceof ActiveQuery) {
                $relQuery->defaultOrder();
            }
            $items = $relQuery->all();
        }
        $label = null;
        if ($model instanceof \netis\utils\crud\ActiveRecord) {
            $label = $model->getRelationLabel($activeRelation, $relation);
        }
        return [
            'widgetClass' => 'maddoger\widgets\Select2',
            'attribute' => $isMany ? $relation : $foreignKey,
            'options' => [
                'label' => $label,
                'items' => $items,
                'clientOptions' => array_merge(
                    [
                        'formatResult' => new JsExpression('function (result, container, query, escapeMarkup, depth) {
    return s2helper.formatResult(result.'.$labelField.', container, query, escapeMarkup, depth);
    }'),
                        'formatSelection' => new JsExpression('function (item) { return item.'.$labelField.'; }'),

                        'width' => '100%',
                        'allowClear' => $multiple || $isMany
                            ? true : (!isset($dbColumns[$foreignKey]) || $dbColumns[$foreignKey]->allowNull),
                        'closeOnSelect' => true,
                    ],
                    $multiple && $items === null ? ['multiple' => true] : [],
                    $route === null ? [] : [
                        'ajax' => [
                            'url' => Url::toRoute([
                                $route . '/index',
                                '_format' => 'json',
                                'fields' => implode(',', $fields),
                            ]),
                            'dataFormat' => 'json',
                            'quietMillis' => 300,
                            'data' => new JsExpression('s2helper.data'),
                            'results' => new JsExpression('s2helper.results'),
                        ],
                        'initSelection' => new JsExpression($multiple ? 's2helper.initMulti' : 's2helper.initSingle'),
                    ]
                ),
                'options' => array_merge([
                    'class' => 'select2',
                    'value' => $value,
                ], $multiple && $items !== null ? ['multiple' => 'multiple'] : []),
            ],
        ];
    }

    /**
     * @param array $formFields
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param array $dbColumns
     * @param array $hiddenAttributes
     * @param array $blameableAttributes
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @return array
     * @throws InvalidConfigException
     */
    protected static function addRelationField($formFields, $model, $relation, $dbColumns, $hiddenAttributes, $blameableAttributes, $multiple = false)
    {
        $activeRelation = $model->getRelation($relation);
        $isHidden = false;
        foreach ($activeRelation->link as $left => $right) {
            if (in_array($right, $blameableAttributes)) {
                return $formFields;
            }
            if (isset($hiddenAttributes[$right])) {
                $formFields[$relation] = Html::activeHiddenInput($model, $right);
                unset($hiddenAttributes[$right]);
                $isHidden = true;
            }
        }
        if ($isHidden) {
            return $formFields;
        }

        if (!Yii::$app->user->can($activeRelation->modelClass.'.read')) {
            return $formFields;
        }

        if (count($activeRelation->link) > 1) {
            throw new InvalidConfigException('Composite hasOne relations are not supported by '.get_called_class());
        }

        if ($activeRelation->multiple) {
            if (($field = static::getHasManyRelationField($model, $relation, $dbColumns, $activeRelation, $multiple)) !== null) {
                $formFields[$relation] = $field;
            }
        } else {
            if (($field = static::getHasOneRelationField($model, $relation, $dbColumns, $activeRelation, $multiple)) !== null) {
                $formFields[$relation] = $field;
            }
        }
        return $formFields;
    }

    /**
     * @param array $formFields
     * @param \yii\db\ActiveRecord $model
     * @param string $attribute
     * @param array $dbColumns
     * @param array $hiddenAttributes
     * @param array $formats
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @return array
     * @throws InvalidConfigException
     */
    protected static function addFormField($formFields, $model, $attribute, $dbColumns, $hiddenAttributes, $formats, $multiple = false)
    {
        if (isset($hiddenAttributes[$attribute])) {
            $formFields[$attribute] = Html::activeHiddenInput($model, $attribute);
            return $formFields;
        }
        $field = [
            'attribute' => $attribute,
            'arguments' => [],
        ];
        $format = is_array($formats[$attribute]) ? $formats[$attribute][0] : $formats[$attribute];
        switch ($format) {
            case 'boolean':
                if ($multiple) {
                    $field['formMethod'] = function ($field, $arguments) {
                        return $field->inline()->radioList([
                            '0' => Yii::$app->formatter->booleanFormat[0],
                            '1' => Yii::$app->formatter->booleanFormat[1],
                            '' => Yii::t('app', 'Any'),
                        ]);
                    };
                } else {
                    $field['formMethod'] = 'checkbox';
                }
                break;
            case 'time':
                $field['formMethod'] = 'textInput';
                $field['arguments'] = [
                    [
                        'value' => Html::encode($model->getAttribute($attribute)),
                    ],
                ];
                break;
            case 'datetime':
            case 'date':
                $field['widgetClass'] = 'omnilight\widgets\DatePicker';
                $field['options'] = [
                    'model' => $model,
                    'attribute' => $attribute,
                    'options' => ['class' => 'form-control']
                ];
                break;
            case 'enum':
                //! @todo move to default case, check if enum with such name exists and add items to arguments
                $field['formMethod'] = 'dropDownList';
                $field['arguments'] = [
                    // first argument is the items array
                    Yii::$app->formatter->getEnums()->get($formats[$attribute][1]),
                ];
                if (isset($dbColumns[$attribute]) && $dbColumns[$attribute]->allowNull) {
                    $field['arguments'][] = [
                        'empty' => Yii::$app->formatter->nullDisplay,
                    ];
                }
                break;
            case 'flags':
                throw new InvalidConfigException('Flags format is not supported by '.get_called_class());
            case 'paragraphs':
                $field['formMethod'] = 'textarea';
                $field['arguments'] = [
                    [
                        'value' => Html::encode($model->getAttribute($attribute)),
                        'cols' => '80',
                        'rows' => '10',
                    ],
                ];
                break;
            case 'file':
                $field['formMethod'] = 'fileInput';
                $field['arguments'] = [
                    [
                        'value' => $model->getAttribute($attribute),
                    ],
                ];
                break;
            default:
            case 'text':
                $field['formMethod'] = 'textInput';
                $field['arguments'] = [
                    [
                        'value' => Html::encode($model->getAttribute($attribute)),
                    ],
                ];
                if (isset($dbColumns[$attribute]) && $dbColumns[$attribute]->type === 'string'
                    && $dbColumns[$attribute]->size !== null
                ) {
                    $field['arguments'][0]['maxlength'] = $dbColumns[$attribute]->size;
                }
                break;
        }
        $formFields[$attribute] = $field;
        return $formFields;
    }

    /**
     * Retrieves form fields configuration.
     * @param \yii\base\Model $model
     * @param array $fields
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @param array $hiddenAttributes list of attribute names to render as hidden fields
     * @return array form fields
     */
    public static function getFormFields($model, $fields, $multiple = false, $hiddenAttributes = [])
    {
        if (!$model instanceof \yii\db\ActiveRecord) {
            return $model->safeAttributes();
        }

        /** @var \netis\utils\crud\ActiveRecord $model */
        $formats = $model->attributeFormats();
        $keys = Action::getModelKeys($model);
        $hiddenAttributes = array_flip($hiddenAttributes);

        list($behaviorAttributes, $blameableAttributes) = Action::getModelBehaviorAttributes($model);
        $dbColumns = $model->getTableSchema()->columns;
        $attributes = $model->safeAttributes();
        $relations = $model->relations();

        $formFields = [];
        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                $formFields[$key] = $field;
                continue;
            } elseif (!is_string($field) && is_callable($field)) {
                $formFields[$key] = call_user_func($field, $model);
                continue;
            }
            if (in_array($field, $relations)) {
                $formFields = static::addRelationField($formFields, $model, $field, $dbColumns, $hiddenAttributes, $blameableAttributes, $multiple);
            } elseif (in_array($field, $attributes)) {
                if (in_array($field, $keys) || (in_array($field, $behaviorAttributes))) {
                    continue;
                }
                $formFields = static::addFormField($formFields, $model, $field, $dbColumns, $hiddenAttributes, $formats, $multiple);
            }
        }

        return $formFields;
    }

    /**
     * @param \yii\web\View $view
     * @param \yii\db\ActiveRecord $model
     * @param \yii\widgets\ActiveForm $form
     * @param string $name
     * @param array $data
     */
    public static function renderControlGroup($view, $model, $form, $name, $data)
    {
        if (isset($data['model'])) {
            $model = $data['model'];
        }
        $field = $form->field($model, $data['attribute']);
        if (isset($data['formMethod'])) {
            if (is_string($data['formMethod'])) {
                echo call_user_func_array([$field, $data['formMethod']], $data['arguments']);
            } else {
                echo call_user_func($data['formMethod'], $field, $data['arguments']);
            }
            return;
        }
        if (isset($data['options']['label'])) {
            $label = $data['options']['label'];
            unset($data['options']['label']);
        } else {
            $label = $model->getAttributeLabel($name);
        }
        $errorClass = $model->getErrors($data['attribute']) !== [] ? 'error' : '';
        echo $field
            ->label($label, ['class' => 'control-label'])
            ->error(['class' => 'help-block'])
            ->widget($data['widgetClass'], $data['options']);
        return;
    }

    /**
     * @param \yii\web\View $view
     * @param \yii\db\ActiveRecord $model
     * @param \yii\widgets\ActiveForm $form
     * @param array $fields
     * @param int $topColumnWidth
     */
    public static function renderRow($view, $model, $form, $fields, $topColumnWidth = 12)
    {
        if (empty($fields)) {
            return;
        }
        $oneColumn = count($fields) == 1;
        echo $oneColumn ? '' : '<div class="row">';
        $columnWidth = ceil($topColumnWidth / count($fields));
        foreach ($fields as $name => $column) {
            echo $oneColumn ? '' : '<div class="col-lg-' . $columnWidth . '">';
            if (is_string($column)) {
                echo $column;
            } elseif (!is_numeric($name) && isset($column['attribute'])) {
                static::renderControlGroup($view, $model, $form, $name, $column);
            } else {
                foreach ($column as $name2 => $row) {
                    if (is_string($row)) {
                        echo $row;
                    } elseif (!is_numeric($name2) && isset($row['attribute'])) {
                        static::renderControlGroup($view, $model, $form, $name2, $row);
                    } else {
                        static::renderRow($view, $model, $form, $row);
                    }
                }
            }
            echo $oneColumn ? '' : '</div>';
        }
        echo $oneColumn ? '' : '</div>';
    }

    /**
     * @param \yii\web\View $view
     * @param \yii\db\ActiveRecord $model
     * @param array $relations
     * @param string $relationName
     * @param string $activeRelation name of currently active relation (first one)
     */
    public static function renderRelation($view, $model, $relations, $relationName, $activeRelation)
    {
        $data = $relations[$relationName];
        /** @var \yii\db\ActiveRecord $relatedModel */
        $relatedModel = $data['model'];
        $createRoute = null;
        $searchRoute = null;
        if (($route = Yii::$app->crudModelsMap[$relatedModel::className()]) !== null) {
            $inverseOf = $data['dataProvider']->query->inverseOf;
            $searchRoute = Url::toRoute([
                $route . '/relation',
                'per-page' => 10,
                'relation' => $inverseOf,
                'id' => Action::exportKey($model->getPrimaryKey()),
            ]);
            //! @todo enable this route only if current record is not new or related model can have null fk
            $createRoute = Url::toRoute([
                $route . '/update',
                'hide' => implode(',', array_keys($data['dataProvider']->query->link)),
                $relatedModel->formName() => array_combine(
                    array_keys($data['dataProvider']->query->link),
                    $model->getPrimaryKey(true)
                ),
            ]);
        }
        $parts = explode('\\', $relatedModel::className());
        $relatedModelClass = array_pop($parts);
        $relatedSearchModelClass = implode('\\', $parts) . '\\search\\' . $relatedModelClass;
        echo Html::activeHiddenInput($model, $relationName.'[add]');
        echo Html::activeHiddenInput($model, $relationName.'[remove]');
        echo $view->render('_relation_widget', [
            'model' => $model,
            'relations' => $relations,
            'relationName' => $relationName,
            'isActive' => $relationName === $activeRelation,
            'buttons' => array_merge(
                $createRoute === null ? [] : [
                    Html::a('<span class="glyphicon glyphicon-file"></span>', '#', [
                        'title'         => Yii::t('app', 'Create new'),
                        'aria-label'    => Yii::t('app', 'Create new'),
                        'data-pjax'     => '0',
                        'data-toggle'   => 'modal',
                        'data-target'   => '#relationModal',
                        'data-relation' => $relationName,
                        'data-title'    => $relatedModel->getCrudLabel('create'),
                        'data-pjax-url' => $createRoute,
                        'class'         => 'btn btn-default',
                    ]),
                ],
                $searchRoute === null || !class_exists($relatedSearchModelClass) ? [] : [
                    Html::a('<span class="glyphicon glyphicon-plus"></span>', '#', [
                        'title'         => Yii::t('app', 'Add existing'),
                        'aria-label'    => Yii::t('app', 'Add existing'),
                        'data-pjax'     => '0',
                        'data-toggle'   => 'modal',
                        'data-target'   => '#relationModal',
                        'data-relation' => $relationName,
                        'data-title'    => $relatedModel->getCrudLabel('index'),
                        'data-pjax-url' => $searchRoute,
                        'class'         => 'btn btn-default',
                    ]),
                ]
            ),
        ]);
    }
}
