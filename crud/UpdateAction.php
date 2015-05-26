<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Model;
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

        if (Yii::$app->request->isAjax) {
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
            'fields' => $this->getFormFields(),
        ];
    }

    /**
     * Retrieves form fields configuration using the modelClass.
     * @return array form fields
     */
    protected function getFormFields()
    {
        if (!$this->controller instanceof ActiveController) {
            /** @var \yii\db\BaseActiveRecord $model */
            $model = new $this->modelClass;

            return $model->attributes();
        }

        /** @var ActiveRecord $model */
        $model = new $this->controller->modelClass();
        $formats = $model->attributeFormats();
        $columns = [];
        $keys = $this->getModelKeys($model);
        list($behaviorAttributes, $blameableAttributes) = $this->getModelBehaviorAttributes($model);
        foreach ($model->safeAttributes() as $attribute) {
            if (in_array($attribute, $keys) || in_array($attribute, $behaviorAttributes)) {
                continue;
            }
            $columns[] = $attribute.':'.$formats[$attribute];
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

            if (!Yii::$app->user->can($activeRelation->modelClass.'.read')) {
                continue;
            }
            $columns[] = [
                'attribute' => $relation,
                'format' => 'crudLink',
                'visible' => true,
            ];
        }

        $actionColumn = new ActionColumn();
        return array_merge([
            [
                'class' => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
                /*'buttons' => [
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
                ],*/
            ],
            [
                'class' => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ],
        ], $columns);

        /*
        $formControls = array();
        $dbColumns = $model->getTableSchema()->columns;
        $safeAttributes = array_flip($model->getSafeAttributeNames());
        $hiddenAttributes = array_flip($model->getHiddenAttributeNames());
        foreach($relations as $name => $relation) {
            $fk = $relation['activeRelation']->foreignKey;
            if(is_array($fk)) {
                foreach($fk as $key => $value) {
                    $fk = is_numeric($key) ? $value : $key;
                }
            }

            if(isset($hiddenAttributes[$fk])) {
                $formControls[$name] = [
                    'formMethod' => 'hiddenField',
                    'attribute' => $fk,
                    'options' => [
                        'value' => $model->{$fk}
                    ]
                ];
                unset($hiddenAttributes[$fk]);
                continue;
            }

            if($relation['relationClass'] != NetActiveRecord::BELONGS_TO || !isset($safeAttributes[$fk]))
                continue;

            $formControls[$name] = [
                'widgetClass' => 'ESelect2',
                'attribute' => $fk,
                'options' => [
                    'model' => $model,
                    'attribute' => $fk,
                    'options' => Select2Helper::filterDefaults("/" . $relation['matchingRelationModel']->defaultController() . "/autocomplete", Yii::t('app', 'Choose...'), true, [
                        'width' => '100%',
                        'allowClear' => !isset($dbColumns[$fk]) || $dbColumns[$fk]->allowNull,
                    ]),
                    'htmlOptions' => ['class' => 'select2']
                ],
            ];
        }

        // hidden attributes have to be hidden, not absent
        foreach($hiddenAttributes as $name => $_) {
            $formControls[$name] = [
                'formMethod' => 'hiddenField',
                'attribute' => $name,
                'options' => [
                    'value' => $model->{$name}
                ]
            ];
        }

        $visibleAttributes = $model->getVisibleAttributes(null, false, false, true);
        foreach($visibleAttributes as $name => $uiTypeInfo) {
            if(isset($dbColumns[$name]) && $dbColumns[$name]->isForeignKey)
                continue;

            $formControls[$name] = ['attribute' => $name, 'options' => []];

            if(is_array($uiTypeInfo)) {
                $formControls[$name]['data'] = $uiTypeInfo['map'];
                $mapSize = count($formControls[$name]['data']);
                $uiType = $uiTypeInfo['type'];
            } else {
                $uiType = $uiTypeInfo;
            }

            switch($uiType) {
                case 'boolean':
                    $formControls[$name]['formMethod'] = 'checkBoxControlGroup';
                    $formControls[$name]['options'] = [
                        'template' => '{beginLabel}{input}<span>{labelTitle}</span>{help}{error}{endLabel}',
                        'class' => 'checkbox style-2',
                    ];
                    break;
                case 'time':
                case 'datetime':
                case 'date':
                    $formControls[$name]['widgetClass'] = 'Datepicker';
                    $formControls[$name]['options'] = [
                        'model' => $model,
                        'attribute' => $name,
                        'htmlOptions' => ['class' => 'form-control']
                    ];
                    break;
                case 'set':
                    $isRadio = $mapSize < 5;
                    if(isset($uiTypeInfo['control']) && $uiTypeInfo !== 'radio') {
                        $isRadio = false;
                    }

                    $formControls[$name]['formMethod'] = $isRadio ? 'radioButtonListControlGroup' : 'dropDownListControlGroup';
                    if(isset($dbColumns[$name]) && $dbColumns[$name]->allowNull) {
                        $formControls[$name]['options'] = $isRadio ? ['uncheckValue' => null] : ['empty' => Yii::t('app', 'Any')];
                    }

                    if($isRadio) {
                        $formControls[$name]['options']['template'] = '<div class="radio">{beginLabel}{input}<span>{labelTitle}</span>{endLabel}</div>';
                        $formControls[$name]['options']['class'] = 'radiobox style-2';
                    }
                    break;
                case 'flags':
                    throw new CException('nie da się przekazać wartości, trzeba deserializować');
                case 'longtext':
                    $formControls[$name]['formMethod'] = 'textAreaControlGroup';
                    $formControls[$name]['options'] = ['value' => Yii::app()->format->format($model->{$name}, 'text'), 'cols' => '80', 'rows' => '10'];
                    break;
                case 'file':
                    $formControls[$name]['formMethod'] = 'fileFieldControlGroup';
                    $formControls[$name]['options'] = ['value' => $model->{$name}];
                    break;
                default:
                case 'text':
                case 'password':
                    $formControls[$name]['formMethod'] = $uiType === 'password' ? 'passwordFieldControlGroup' : 'textFieldControlGroup';
                    $formControls[$name]['options'] = ['value' => Yii::app()->format->format($model->{$name}, $uiType)];
                    if(isset($dbColumns[$name]) && $dbColumns[$name]->type === 'string' && $dbColumns[$name]->size !== null) {
                        $formControls[$name]['options']['maxlength'] = $dbColumns[$name]->size;
                    }
                    break;
            }
        */
    }
}
