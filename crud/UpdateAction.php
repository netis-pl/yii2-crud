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

        if (Yii::$app->request->isAjax && !Yii::$app->request->isPjax) {
            $response = clone Yii::$app->response;
            $response->format = Response::FORMAT_JSON;
            $response->content = json_encode(ActiveForm::validate($model));
            return $response;
        }

        $wasNew = $model->isNewRecord;

        if ($model->load(Yii::$app->getRequest()->getBodyParams())) {
            if ($model->save()) {
                $response = Yii::$app->getResponse();
                $response->setStatusCode(201);

                if ($wasNew) {
                    $message = Yii::t('app', 'A new has been successfully created.');
                } else {
                    $message = Yii::t('app', 'Record has been successfully updated.');
                }
                $this->setFlash('success', $message);

                $id = $this->exportKey($model->getPrimaryKey(true));
                $response->getHeaders()->set('Location', Url::toRoute([$this->viewAction, 'id' => $id], true));
            } elseif (!$model->hasErrors()) {
                throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
            }
        }

        return [
            'model' => $model,
            'fields' => $this->getFormFields($model),
            'relations' => $this->getModelRelations($model),
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
        if ($activeRelation->multiple) {
            return $formFields;
        }
        $isHidden = false;
        foreach ($activeRelation->link as $left => $right) {
            if (in_array($left, $blameableAttributes)) {
                return $formFields;
            }
            if (isset($hiddenAttributes[$left])) {
                $formFields[$relation] = [
                    'formMethod' => 'hiddenField',
                    'attribute' => $left,
                    'options' => [
                        'value' => $model->{$left}
                    ]
                ];
                unset($hiddenAttributes[$left]);
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
        $foreignKeys = array_keys($activeRelation->link);
        $foreignKey = reset($foreignKeys);

        $route = Yii::$app->crudModelsMap[$activeRelation->modelClass];
        $formFields[$relation] = [
            'widgetClass' => 'maddoger\widgets\Select2',
            'attribute' => $foreignKey,
            'options' => [
                'clientOptions' => [
                    'ajax' => [
                        'url' => Url::toRoute($route),
                        'dataFormat' => 'json',
                        'quietMillis' => 300,
                        //'data' => 'js:s2helper.data',
                        //'results' => 'js:s2helper.results',
                    ],
                    //'initSelection' => 'js:s2helper.initSingle',
                    //'formatResult' => 'js:s2helper.formatResult',
                    //'formatSelection' => 'js:function (item) { return item.value; }',

                    'width' => '100%',
                    'allowClear' => !isset($dbColumns[$foreignKey]) || $dbColumns[$foreignKey]->allowNull,
                    'closeOnSelect' => true,
                ],
                'options' => [
                    'class' => 'select2',
                    'multiple' => false,
                ],
            ],
        ];
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
        $formFields[$attribute] = [
            'attribute' => $attribute,
            'options' => [],
        ];

        switch ($formats[$attribute]) {
            case 'boolean':
                $formFields[$attribute]['formMethod'] = 'checkbox';
                break;
            case 'time':
                $formFields[$attribute]['formMethod'] = 'textInput';
                $formFields[$attribute]['options'] = [
                    'value' => Html::encode($model->getAttribute($attribute)),
                ];
                break;
            case 'datetime':
            case 'date':
                $formFields[$attribute]['widgetClass'] = 'omnilight\widgets\DatePicker';
                $formFields[$attribute]['options'] = [
                    'model' => $model,
                    'attribute' => $attribute,
                    'htmlOptions' => ['class' => 'form-control']
                ];
                break;
            case 'set':
                $formFields[$attribute]['formMethod'] = 'listBox';
                if (isset($dbColumns[$attribute]) && $dbColumns[$attribute]->allowNull) {
                    $formFields[$attribute]['options'] = ['empty' => Yii::t('app', 'Any')];
                }
                break;
            case 'flags':
                throw new InvalidConfigException('Flags format is not supported by '.get_called_class());
            case 'paragraphs':
                $formFields[$attribute]['formMethod'] = 'textarea';
                $formFields[$attribute]['options'] = [
                    'value' => Html::encode($model->getAttribute($attribute)),
                    'cols' => '80',
                    'rows' => '10',
                ];
                break;
            case 'file':
                $formFields[$attribute]['formMethod'] = 'fileInput';
                $formFields[$attribute]['options'] = [
                    'value' => $model->getAttribute($attribute),
                ];
                break;
            default:
            case 'text':
                $formFields[$attribute]['formMethod'] = 'textInput';
                $formFields[$attribute]['options'] = [
                    'value' => Html::encode($model->getAttribute($attribute)),
                ];
                if (isset($dbColumns[$attribute]) && $dbColumns[$attribute]->type === 'string'
                    && $dbColumns[$attribute]->size !== null
                ) {
                    $formFields[$attribute]['options']['maxlength'] = $dbColumns[$attribute]->size;
                }
                break;
        }
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
