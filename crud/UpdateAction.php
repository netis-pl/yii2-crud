<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\widgets\ActiveForm;

/**
 * Combines the \yii\rest\UpdateAction and \yii\rest\CreateAction.
 * @package netis\utils\crud
 */
class UpdateAction extends Action
{
    /**
     * @var string the scenario to be assigned to the model before it is validated and updated.
     */
    public $scenario = Model::SCENARIO_DEFAULT;
    /**
     * @var string the name of the view action. This property is need to create the URL
     * when the model is successfully created.
     */
    public $viewAction = 'view';


    /**
     * Updates an existing model or creates a new one if $id is null.
     * @param string $id the primary key of the model.
     * @return \yii\db\ActiveRecordInterface the model being updated
     * @throws ServerErrorHttpException if there is any error when updating the model
     */
    public function run($id = null)
    {
        /* @var $model ActiveRecord */
        if ($id === null) {
            $model = new $this->modelClass(['scenario' => $this->scenario]);
        } else {
            $model = $this->findModel($id);
            $model->scenario = $this->scenario;
        }

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }

        $wasNew = $model->isNewRecord;

        if ($model->load(Yii::$app->getRequest()->getBodyParams())) {
            if (Yii::$app->request->isAjax && !Yii::$app->request->isPjax) {
                $response = clone Yii::$app->response;
                $response->format = Response::FORMAT_JSON;
                $response->content = json_encode(ActiveForm::validate($model));
                return $response;
            }
            if ($model->validate()) {
                $trx = $model->getDb()->beginTransaction();
                if (!$model->save(false) || !$model->saveRelations(Yii::$app->getRequest()->getBodyParams())) {
                    throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
                }
                throw new \Exception('test');
                $trx->commit();

                if ($wasNew) {
                    $message = Yii::t('app', 'A new has been successfully created.');
                } else {
                    $message = Yii::t('app', 'Record has been successfully updated.');
                }
                $this->setFlash('success', $message);

                $id = $this->exportKey($model->getPrimaryKey(true));
                $response = Yii::$app->getResponse();
                $response->setStatusCode(201);
                $response->getHeaders()->set('Location', Url::toRoute([$this->viewAction, 'id' => $id], true));
            }
        }

        return [
            'model' => $model,
            'fields' => $this->getFormFields($model),
            'relations' => $this->getModelRelations($model),
        ];
    }

    protected function registerSelect()
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
        $this->controller->view->registerJs($script, \yii\web\View::POS_END);
    }

    /**
     * @param ActiveRecord $model
     * @param array $dbColumns
     * @param \yii\db\ActiveQuery $activeRelation
     * @return array
     */
    protected function getHasOneRelationField($model, $dbColumns, $relation, $activeRelation)
    {
        return $this->getRelationWidget($model, $dbColumns, $relation, $activeRelation);
    }

    /**
     * To enable this, override and return getRelationWidget().
     * @param ActiveRecord $model
     * @param array $dbColumns
     * @param \yii\db\ActiveQuery $activeRelation
     * @return array
     */
    protected function getHasManyRelationField($model, $dbColumns, $relation, $activeRelation)
    {
        return $this->getRelationWidget($model, $dbColumns, $relation, $activeRelation);
        return null;
    }

    /**
     * @param ActiveRecord $model
     * @param array $dbColumns
     * @param \yii\db\ActiveQuery $activeRelation
     * @return array
     */
    protected function getRelationWidget($model, $dbColumns, $relation, $activeRelation)
    {
        $multiple = $activeRelation->multiple;
        $foreignKeys = array_values($activeRelation->link);
        $foreignKey = reset($foreignKeys);
        $route = Yii::$app->crudModelsMap[$activeRelation->modelClass];
        $relModel = new $activeRelation->modelClass;
        $primaryKey = $relModel->getTableSchema()->primaryKey;
        $fields = array_merge($primaryKey, $relModel->getBehavior('labels')->attributes);
        $labelField = reset($relModel->getBehavior('labels')->attributes);
        if ($multiple) {
            $value = self::implodeEscaped(
                self::KEYS_SEPARATOR,
                array_map([$this, 'exportKey'], $activeRelation->select($primaryKey)->asArray()->all())
            );
        } else {
            $value = self::exportKey($model->getAttributes($foreignKeys));
        }
        return [
            'widgetClass' => 'maddoger\widgets\Select2',
            'attribute' => $multiple ? $relation : $foreignKey,
            'options' => [
                'items' => $route !== null ? null : $relModel::find()->defaultOrder()->all(),
                'clientOptions' => [
                    'ajax' => $route === null ? [] : [
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
                    'formatResult' => new JsExpression('function (result, container, query, escapeMarkup, depth) {
return s2helper.formatResult(result.'.$labelField.', container, query, escapeMarkup, depth);
}'),
                    'formatSelection' => new JsExpression('function (item) { return item.'.$labelField.'; }'),

                    'width' => '100%',
                    'allowClear' => $multiple
                        ? true : (!isset($dbColumns[$foreignKey]) || $dbColumns[$foreignKey]->allowNull),
                    'closeOnSelect' => true,
                    'multiple' => $multiple,
                ],
                'options' => [
                    'class' => 'select2',
                    'value' => $value,
                ],
            ],
        ];
    }

    /**
     * @param array $formFields
     * @param ActiveRecord $model
     * @param string $relation
     * @param array $dbColumns
     * @param array $hiddenAttributes
     * @param array $blameableAttributes
     * @return array
     * @throws InvalidConfigException
     */
    protected function addRelationField($formFields, $model, $relation, $dbColumns, $hiddenAttributes, $blameableAttributes)
    {
        $activeRelation = $model->getRelation($relation);
        $isHidden = false;
        $foreignKey = null;
        foreach ($activeRelation->link as $left => $right) {
            if (in_array($right, $blameableAttributes)) {
                return $formFields;
            }
            if (isset($hiddenAttributes[$right])) {
                $formFields[$relation] = [
                    'formMethod' => 'hiddenField',
                    'attribute' => $right,
                    'options' => [
                        'value' => $model->{$right}
                    ]
                ];
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

        $this->registerSelect();
        if ($activeRelation->multiple) {
            if (($field = $this->getHasManyRelationField($model, $dbColumns, $relation, $activeRelation)) !== null) {
                $formFields[$relation] = $field;
            }
        } else {
            if (($field = $this->getHasOneRelationField($model, $dbColumns, $relation, $activeRelation)) !== null) {
                $formFields[$relation] = $field;
            }
        }
        return $formFields;
    }

    /**
     * @param array $formFields
     * @param ActiveRecord $model
     * @param string $attribute
     * @param array $dbColumns
     * @param array $formats
     * @return array
     * @throws InvalidConfigException
     */
    protected function addFormField($formFields, $model, $attribute, $dbColumns, $formats)
    {
        $field = [
            'attribute' => $attribute,
            'arguments' => [],
        ];

        switch ($formats[$attribute]) {
            case 'boolean':
                $field['formMethod'] = 'checkbox';
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
            case 'set':
                //! @todo move to default case, check if enum with such name exists and add items to arguments
                $field['formMethod'] = 'listBox';
                $field['arguments'] = [
                    [], // first argument is the items array
                ];
                if (isset($dbColumns[$attribute]) && $dbColumns[$attribute]->allowNull) {
                    $field['arguments'][] = [
                        'empty' => Yii::t('app', 'Any'),
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
     * @param Model $model
     * @return array form fields
     */
    protected function getFormFields($model)
    {
        if (!$model instanceof ActiveRecord) {
            return $model->safeAttributes();
        }

        /** @var ActiveRecord $model */
        $hiddenAttributes = [];
        $formats = $model->attributeFormats();
        $keys = self::getModelKeys($model);

        list($behaviorAttributes, $blameableAttributes) = $this->getModelBehaviorAttributes($model);
        $dbColumns = $model->getTableSchema()->columns;

        $formFields = [];
        foreach ($model->relations() as $relation) {
            $formFields = $this->addRelationField($formFields, $model, $relation, $dbColumns, $hiddenAttributes, $blameableAttributes);
        }
        // hidden attributes have to be hidden, not absent
        foreach ($hiddenAttributes as $attribute => $_) {
            $formFields[$attribute] = [
                'formMethod' => 'hiddenField',
                'attribute' => $attribute,
                'options' => [
                    'value' => $model->getAttribute($attribute),
                ]
            ];
        }
        foreach ($model->safeAttributes() as $attribute) {
            if (in_array($attribute, $keys) || in_array($attribute, $behaviorAttributes)
                || isset($hiddenAttributes[$attribute])
            ) {
                continue;
            }
            $formFields = $this->addFormField($formFields, $model, $attribute, $dbColumns, $formats);
        }

        return $formFields;
    }
}
