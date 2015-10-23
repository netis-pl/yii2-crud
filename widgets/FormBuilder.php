<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\widgets;

use netis\utils\crud\Action;
use netis\utils\db\ActiveQuery;
use netis\utils\web\Formatter;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;

class FormBuilder
{
    const MODAL_MODE_NEW_RECORD = 1;
    const MODAL_MODE_EXISTING_RECORD = 2;

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
        window.Select2.util.markMatch(result._label, query.term, markup, escapeMarkup);
        return markup.join("");
    };

    s2helper.formatSelection = function (item) {
        return item._label;
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
        var params = {
            search: {
                id: element.val()
            }
        }
        $.getJSON(element.data('select2').opts.ajax.url, params, function (data) {
            if (typeof data.items[0] != 'undefined')
                callback(data.items[0]);
        });
    };

    s2helper.initMulti = function (element, callback) {
        var params = {
            search: {
                id: element.val()
            }
        }
        $.getJSON(element.data('select2').opts.ajax.url, params, function (data) {callback(data.items);});
    };
}( window.s2helper = window.s2helper || {}, jQuery ));
JavaScript;
        $view->registerJs($script, \yii\web\View::POS_END, 'netis.s2helper');
        \maddoger\widgets\Select2BootstrapAsset::register($view);
    }

    /**
     * Registers JS code for handling relations.
     * @param \yii\web\View $view
     * @return string modal widget to be embedded in a view
     */
    public static function registerRelations($view)
    {
        \netis\utils\assets\RelationsAsset::register($view);
        $options = \yii\helpers\Json::htmlEncode([
            'i18n'                  => [
                'loadingText' => Yii::t('app', 'Loading, please wait.'),
            ],
            'keysSeparator'         => \netis\utils\crud\Action::KEYS_SEPARATOR,
            'compositeKeySeparator' => \netis\utils\crud\Action::COMPOSITE_KEY_SEPARATOR,
        ]);
        $view->registerJs("netis.init($options)", \yii\web\View::POS_READY, 'netis.init');

        // init relation tools used in _relations subview
        // relations modal may contain a form and must be rendered outside ActiveForm
        return \yii\bootstrap\Modal::widget([
            'id'     => 'relationModal',
            'size'   => \yii\bootstrap\Modal::SIZE_LARGE,
            'header' => '<span class="modal-title"></span>',
            'footer' => implode('', [
                Html::button(Yii::t('app', 'Save'), [
                    'id'    => 'relationSave',
                    'class' => 'btn btn-primary',
                ]),
                Html::button(Yii::t('app', 'Cancel'), [
                    'class'        => 'btn btn-default',
                    'data-dismiss' => 'modal',
                    'aria-hidden'  => 'true',
                ]),
            ]),
        ]);
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
     * @return array
     */
    protected static function getHasManyRelationField($model, $relation, $dbColumns, $activeRelation)
    {
        return static::getRelationWidget($model, $relation, $dbColumns, $activeRelation, true);
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param \yii\db\ActiveRecord $relatedModel
     * @param \yii\db\ActiveQuery $relation
     * @return array array with three arrays: create, search and index routes
     */
    public static function getRelationRoutes($model, $relatedModel, $relation)
    {
        if (($route = Yii::$app->crudModelsMap[$relatedModel::className()]) === null) {
            return [null, null, null];
        }

        $allowCreate = Yii::$app->user->can($relatedModel::className().'.create');
        if ($allowCreate && $model->isNewRecord && $relation->multiple) {
            foreach ($relation->link as $left => $right) {
                if (!$relatedModel->getTableSchema()->getColumn($left)->allowNull) {
                    $allowCreate = false;
                    break;
                }
            }
        }

        $createRoute = !$allowCreate ? null : [
            $route . '/update',
            'hide'                    => implode(',', array_keys($relation->link)),
            $relatedModel->formName() => array_combine(
                array_keys($relation->link),
                $model->getPrimaryKey(true)
            ),
        ];

        $parts = explode('\\', $relatedModel::className());
        $relatedModelClass = array_pop($parts);
        $relatedSearchModelClass = implode('\\', $parts) . '\\search\\' . $relatedModelClass;
        $searchRoute = !class_exists($relatedSearchModelClass) ? null : [
            $route . '/relation',
            'per-page' => 10,
            'relation' => $relation->inverseOf,
            'id'       => Action::exportKey($model->getPrimaryKey()),
            'multiple' => $relation->multiple ? 'true' : 'false',
        ];

        $indexRoute = [
            $route . '/index',
        ];

        return [$createRoute, $searchRoute, $indexRoute];
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
        /** @var \netis\utils\crud\ActiveRecord $relModel */
        $relModel = new $activeRelation->modelClass;
        $primaryKey = $relModel::primaryKey();
        if (($labelAttributes = $relModel->getBehavior('labels')->attributes) !== null) {
            $fields = array_merge($primaryKey, $labelAttributes);
        } else {
            $fields = $primaryKey;
        }
        $jsPrimaryKey = json_encode($primaryKey);
        $jsSeparator = \netis\utils\crud\Action::COMPOSITE_KEY_SEPARATOR;
        $jsId = <<<JavaScript
function(object){
    var keys = $jsPrimaryKey, values = [];
    for (var i = 0; i < keys.length; i++) {
         values.push(object[keys[i]]);
    }
    return netis.implodeEscaped('$jsSeparator', values);
}
JavaScript;
        if ($isMany) {
            if (property_exists($model, $relation)) {
                // special case for search models, where there is a relation property defined that holds the keys
                $value = $model->$relation;
            } else {
                $value = array_map(
                    '\netis\utils\crud\Action::exportKey',
                    $activeRelation->select($primaryKey)->asArray()->all()
                );
            }
            if (is_array($value)) {
                $value = Action::implodeEscaped(Action::KEYS_SEPARATOR, $value);
            }
        } else {
            // special case for search models, where fks holds array of keys
            if (is_array($model->getAttribute($foreignKey))) {
                if (count($foreignKeys) > 1) {
                    throw new Exception('Composite foreign keys are not supported for searching.');
                }
                $value = Action::implodeEscaped(Action::KEYS_SEPARATOR, $model->getAttribute($foreignKey));
            } else {
                $value = Action::exportKey($model->getAttributes($foreignKeys));
            }
        }
        list($createRoute, $searchRoute, $indexRoute) = FormBuilder::getRelationRoutes(
            $model,
            $relModel,
            $activeRelation
        );
        $items = null;
        if ($indexRoute === null) {
            $relQuery = $relModel::find();
            if ($relQuery instanceof ActiveQuery) {
                $relQuery->defaultOrder();
            }
            $flippedPrimaryKey = array_flip($primaryKey);
            $items = ArrayHelper::map(
                $relQuery
                    ->from($relModel::tableName() . ' t')
                    ->authorized($relModel, $relModel->getCheckedRelations(), Yii::$app->user->getIdentity())
                    ->all(),
                function ($item) use ($fields, $flippedPrimaryKey) {
                    /** @var \netis\utils\crud\ActiveRecord $item */
                    return Action::exportKey(array_intersect_key($item->toArray($fields, []), $flippedPrimaryKey));
                },
                function ($item) use ($fields) {
                    /** @var \netis\utils\crud\ActiveRecord $item */
                    $data = $item->toArray($fields, []);
                    return $data['_label'];
                }
            );
        }
        $label = null;
        if ($model instanceof \netis\utils\crud\ActiveRecord) {
            $label = $model->getRelationLabel($activeRelation, $relation);
        }
        $ajaxResults = new JsExpression('s2helper.results');
        $clientEvents = null;
        if ($indexRoute !== null && ($searchRoute !== null || $createRoute !== null)) {
            $searchLabel = Yii::t('app', 'Advanced search');
            $createLabel = Yii::t('app', 'Create new');
            $searchUrl = $searchRoute === null ? null : Url::toRoute($searchRoute);
            $createUrl = $createRoute === null ? null : Url::toRoute($createRoute);
            $createKey = 'create_item';
            $searchKey = 'search_item';
            $script = <<<JavaScript
function (data, page) {
    var keys = $jsPrimaryKey, values = {};
    if ('$searchUrl') {
        for (var i = 0; i < keys.length; i++) {
            values[keys[i]] = '$searchKey';
        }
        values._label = '-- $searchLabel --';
        data.items.unshift(values);
    }
    if ('$createUrl') {
        values = [];
        for (var i = 0; i < keys.length; i++) {
            values[keys[i]] = '$createKey';
        }
        values._label = '-- $createLabel --';
        data.items.unshift(values);
    }
    return s2helper.results(data, page);
}
JavaScript;
            $ajaxResults = new JsExpression($script);
            $script = <<<JavaScript
function (event) {
    var isSearch = true, isCreate = true;
    if (event.val != '$searchKey') {
        isSearch = false;
    }

    if (event.val != '$createKey') {
        isCreate = false;
    }

    if (!isSearch && !isCreate) {
        return true;
    }

    $(event.target).select2('close');
    $('#relationModal').data('target', $(event.target).attr('id'));
    $('#relationModal').data('title', '$label');
    $('#relationModal').data('relation', '$relation');
    $('#relationModal').data('pjax-url', isSearch ? '$searchUrl' : '$createUrl');
    $('#relationModal').modal('show');
    event.preventDefault();
    return false;
}
JavaScript;
            $clientEvents = [
                'select2-selecting' => new JsExpression($script),
            ];
        }

        $allowClear = $multiple || $isMany ? true : !$model->isAttributeRequired($foreignKey)
            && (!isset($dbColumns[$foreignKey]) || $dbColumns[$foreignKey]->allowNull);

        if (!$allowClear && trim($value) === '') {
            Yii::$app->user->can($activeRelation->modelClass . '.read');
            $relQuery = $relModel::find()
                ->select($primaryKey)
                ->from($relModel::tableName() . ' t')
                ->authorized($relModel, $relModel->getCheckedRelations(), Yii::$app->user->getIdentity())
                ->asArray();
            if ($relQuery->count() === 1) {
                $value = $relQuery->one();
                $value = Action::implodeEscaped(Action::KEYS_SEPARATOR, $value);
            }
        }

        return [
            'widgetClass' => 'maddoger\widgets\Select2',
            'attribute' => $isMany ? $relation : $foreignKey,
            'options' => [
                'label' => $label,
                'items' => $items,
                'clientOptions' => array_merge(
                    $indexRoute === null ? [] : [
                        'formatResult' => new JsExpression('s2helper.formatResult'),
                        'formatSelection' => new JsExpression('s2helper.formatSelection'),
                    ],
                    $items === null ? ['id' => new JsExpression($jsId)] : [],
                    [
                        'width' => '100%',
                        'allowClear' => $allowClear,
                        'closeOnSelect' => true,
                    ],
                    $multiple && $items === null ? ['multiple' => true] : [],
                    $indexRoute === null ? [] : [
                        'ajax' => [
                            'url' => Url::toRoute(array_merge($indexRoute, [
                                '_format' => 'json',
                                'fields' => implode(',', $fields),
                            ])),
                            'dataFormat' => 'json',
                            'quietMillis' => 300,
                            'data' => new JsExpression('s2helper.data'),
                            'results' => $ajaxResults,
                        ],
                        'initSelection' => new JsExpression($multiple ? 's2helper.initMulti' : 's2helper.initSingle'),
                    ]
                ),
                'clientEvents' => $clientEvents,
                'options' => array_merge([
                    'class' => 'select2',
                    'value' => $value,
                    'placeholder' => self::getPrompt(),
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
     * @param array $safeAttributes
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @return array
     * @throws InvalidConfigException
     */
    protected static function addRelationField($formFields, $model, $relation, $dbColumns, $hiddenAttributes, $safeAttributes, $multiple = false)
    {
        $activeRelation = $model->getRelation($relation);
        if (!$activeRelation->multiple) {
            // validate foreign keys only for hasOne relations
            $isHidden = false;
            foreach ($activeRelation->link as $left => $right) {
                if (!in_array($right, $safeAttributes)) {
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
        }

        if (!Yii::$app->user->can($activeRelation->modelClass.'.read')) {
            return $formFields;
        }

        if (count($activeRelation->link) > 1) {
            throw new InvalidConfigException('Composite key relations are not supported by '.get_called_class());
        }

        if ($activeRelation->multiple) {
            if (($field = static::getHasManyRelationField($model, $relation, $dbColumns, $activeRelation)) !== null) {
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
        $attributeName = Html::getAttributeName($attribute);
        if (isset($hiddenAttributes[$attributeName])) {
            $formFields[$attribute] = Html::activeHiddenInput($model, $attribute);
            return $formFields;
        }
        $field = [
            'attribute' => $attribute,
            'arguments' => [],
        ];
        $format = is_array($formats[$attributeName]) ? $formats[$attributeName][0] : $formats[$attributeName];
        /** @var Formatter $formatter */
        $formatter = Yii::$app->formatter;

        switch ($format) {
            case 'boolean':
                if ($multiple) {
                    $field['formMethod'] = function ($field, $arguments) use ($formatter) {
                        return $field->inline()->radioList([
                            '0' => $formatter->booleanFormat[0],
                            '1' => $formatter->booleanFormat[1],
                            '' => Yii::t('app', 'Any'),
                        ]);
                    };
                } else {
                    $field['formMethod'] = 'checkbox';
                }
                break;
            case 'multiplied':
            case 'integer':
                $value = Html::getAttributeValue($model, $attribute);
                $field['formMethod'] = 'textInput';
                $field['arguments'] = [
                    [
                        'value' => $format === 'multiplied'
                            ? ($value === null ? null : $formatter->asMultiplied($value, $formats[$attributeName][1]))
                            : $value,
                    ],
                ];
                break;
            case 'time':
                $field['formMethod'] = 'textInput';
                $field['arguments'] = [
                    [
                        'value' => Html::encode(Html::getAttributeValue($model, $attribute)),
                    ],
                ];
                break;
            case 'datetime':
            case 'date':
                $value                = Html::getAttributeValue($model, $attribute);
                $field['widgetClass'] = 'omnilight\widgets\DatePicker';
                $field['options']     = [
                    'model'     => $model,
                    'attribute' => $attribute,
                    'options'   => [
                        'class' => 'form-control',
                        'value' => ($value === null ? null : $formatter->asDate($value)),
                    ],
                ];
                break;
            case 'enum':
                //! @todo move to default case, check if enum with such name exists and add items to arguments
                $field['formMethod'] = 'dropDownList';
                $field['arguments'] = [
                    // first argument is the items array
                    $formatter->getEnums()->get($formats[$attributeName][1]),
                ];
                if (isset($dbColumns[$attributeName]) && $dbColumns[$attributeName]->allowNull) {
                    $field['arguments'][] = [
                        'prompt' => self::getPrompt(),
                    ];
                }
                break;
            case 'flags':
                throw new InvalidConfigException('Flags format is not supported by '.get_called_class());
            case 'paragraphs':
                $field['formMethod'] = 'textarea';
                $field['arguments'] = [
                    [
                        'value' => Html::encode(Html::getAttributeValue($model, $attribute)),
                        'cols' => '80',
                        'rows' => '10',
                    ],
                ];
                break;
            case 'file':
                $field['formMethod'] = 'fileInput';
                $field['arguments'] = [
                    [
                        'value' => Html::getAttributeValue($model, $attribute),
                    ],
                ];
                break;
            default:
            case 'text':
                $field['formMethod'] = 'textInput';
                $field['arguments'] = [
                    [
                        'value' => Html::encode(Html::getAttributeValue($model, $attribute)),
                    ],
                ];
                if (isset($dbColumns[$attributeName]) && $dbColumns[$attributeName]->type === 'string'
                    && $dbColumns[$attributeName]->size !== null
                ) {
                    $field['arguments'][0]['maxlength'] = $dbColumns[$attributeName]->size;
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
            $attributeName = Html::getAttributeName($field);

            if (in_array($attributeName, $relations)) {
                $formFields = static::addRelationField(
                    $formFields, $model, $field, $dbColumns,
                    $hiddenAttributes, $attributes, $multiple
                );
            } elseif (in_array($attributeName, $attributes)) {
                if (in_array($attributeName, $keys) || (in_array($attributeName, $behaviorAttributes))) {
                    continue;
                }
                $formFields = static::addFormField(
                    $formFields, $model, $field, $dbColumns,
                    $hiddenAttributes, $formats, $multiple
                );
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
     * @return string
     */
    public static function renderControlGroup($view, $model, $form, $name, $data)
    {
        if (isset($data['model'])) {
            $model = $data['model'];
        }
        if (!isset($data['options'])) {
            $data['options'] = [];
        }
        $attributeName = Html::getAttributeName($name);
        $label = ArrayHelper::remove($data['options'], 'label', $model->getAttributeLabel($attributeName));
        $errorOptions = ArrayHelper::remove($data['options'], 'error', []);
        $field = $form->field($model, $data['attribute'], isset($data['formMethod']) ? $data['options'] : []);
        $field->label($label, ['class' => 'control-label']);
        $field->error($errorOptions);
        if (isset($data['formMethod'])) {
            if (is_string($data['formMethod'])) {
                return call_user_func_array([$field, $data['formMethod']], $data['arguments']);
            } else {
                return call_user_func($data['formMethod'], $field, $data['arguments']);
            }
        }
        $errorClass = $model->getErrors($data['attribute']) !== [] ? 'error' : '';
        $field
            ->error(['class' => 'help-block help-block-error'])
            ->widget($data['widgetClass'], $data['options']);
        return $field;
    }

    /**
     * @param \yii\web\View $view
     * @param \yii\db\ActiveRecord $model
     * @param \yii\widgets\ActiveForm $form
     * @param array $fields
     * @param int $topColumnWidth
     * @return string
     */
    public static function renderRow($view, $model, $form, $fields, $topColumnWidth = 12)
    {
        if (empty($fields)) {
            return '';
        }
        $result = [];
        $oneColumn = false; // optionally: count($fields) == 1;
        $result[] = $oneColumn ? '' : '<div class="row">';
        $columnWidth = ceil($topColumnWidth / count($fields));
        foreach ($fields as $name => $column) {
            $result[] = $oneColumn ? '' : '<div class="col-sm-' . $columnWidth . '">';
            if (is_string($column)) {
                $result[] = $column;
            } elseif (!is_numeric($name) && isset($column['attribute'])) {
                $result[] = static::renderControlGroup($view, $model, $form, $name, $column);
            } else {
                foreach ($column as $name2 => $row) {
                    if (is_string($row)) {
                        $result[] = $row;
                    } elseif (!is_numeric($name2) && isset($row['attribute'])) {
                        $result[] = static::renderControlGroup($view, $model, $form, $name2, $row);
                    } else {
                        $result[] = static::renderRow($view, $model, $form, $row);
                    }
                }
            }
            $result[] = $oneColumn ? '' : '</div>';
        }
        $result[] = $oneColumn ? '' : '</div>';

        return implode('', $result);
    }

    /**
     * @param \yii\base\Model $model
     * @param array[]         $fields
     *
     * @return bool
     */
    public static function hasRequiredFields($model, $fields)
    {
        foreach ($fields as $name => $column) {
            if (is_string($column)) {
                if ($model->isAttributeRequired($column)) {
                    return true;
                }
                continue;
            }

            if (!is_numeric($name) && isset($column['attribute'])) {
                if ($model->isAttributeRequired($column['attribute'])) {
                    return true;
                }
                continue;
            }

            foreach ($column as $name2 => $row) {
                if (is_string($row)) {
                    if ($model->isAttributeRequired($row)) {
                        return true;
                    }
                    continue;
                }

                if (!is_numeric($name2) && isset($row['attribute'])) {
                    if ($model->isAttributeRequired($row['attribute'])) {
                        return true;
                    }
                    continue;
                }

                if (static::hasRequiredFields($model, $row)) {
                    return true;
                }
            }

        }
        return false;
    }

    public static function getPrompt()
    {
        $prompt = null;
        $formatter = Yii::$app->formatter;
        if ($formatter instanceof \netis\utils\web\Formatter) {
            $prompt = $formatter->dropDownPrompt;
        } else {
            $prompt = strip_tags($formatter->nullDisplay);
        }

        if (trim($prompt) === '') {
            throw new InvalidConfigException('Prompt value cannot be empty string!');
        }

        return trim($prompt);
    }
}
