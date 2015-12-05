<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

use netis\crud\widgets\FormBuilder;
use Yii;
use yii\base\Model;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Request;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Combines the \yii\rest\UpdateAction and \yii\rest\CreateAction.
 * @package netis\crud\crud
 */
class UpdateAction extends Action
{
    const CREATE_RELATED_BUTTON = 'create';
    const SEARCH_RELATED_BUTTON = 'search';

    /**
     * @var string the scenario to be assigned to a new model before it is validated and updated.
     */
    public $createScenario = Model::SCENARIO_DEFAULT;
    /**
     * @var string the scenario to be assigned to the existing model before it is validated and updated.
     */
    public $updateScenario = Model::SCENARIO_DEFAULT;
    /**
     * @var string the name of the view action. This property is need to create the URL
     * when the model is successfully created.
     */
    public $viewAction = 'view';

    const NEW_RELATED_BUTTON_NAME = 'createResponseButton';
    const ADD_RELATED_NAME        = 'addRelated';

    /**
     * Updates an existing model or creates a new one if $id is null.
     * @param string $id the primary key of the model.
     * @return \yii\db\ActiveRecordInterface the model being updated
     * @throws ServerErrorHttpException if there is any error when updating the model
     */
    public function run($id = null)
    {
        $model = $this->initModel($id);

        $wasNew = $model->isNewRecord;

        $loaded = $this->load($model, Yii::$app->getRequest());
        // always call this to validate every AJAX call, even if it's a GET
        if (($response = $this->validateAjax($model)) !== false) {
            return $response;
        }
        if ($loaded && $this->beforeSave($model)) {
            $trx = $model->getDb()->beginTransaction();
            if (!$this->save($model)) {
                throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
            }
            $trx->commit();

            $this->afterSave($model, $wasNew);
        }

        return $this->getResponse($model);
    }

    /**
     * Loads the model and performs authorization check.
     * @param string $id
     * @return ActiveRecord
     * @throws \yii\web\NotFoundHttpException
     */
    protected function initModel($id)
    {
        /* @var $model ActiveRecord */
        if ($id === null) {
            $model = new $this->modelClass(['scenario' => $this->createScenario]);
            $model->loadDefaultValues();
        } else {
            $model = $this->findModel($id);
            $model->scenario = $this->updateScenario;
        }

        if ($this->checkAccess) {
            // use only create and update auth item names because more actions are based on this one
            call_user_func($this->checkAccess, $id === null ? 'create' : 'update', $model);
        }
        return $model;
    }

    /**
     * Loads query and post params into the model.
     * @param ActiveRecord $model
     * @param Request $request
     * @return bool true if post params were loaded.
     */
    protected function load($model, $request)
    {
        $model->load($request->getQueryParams());

        return $model->load($request->getBodyParams());
    }

    /**
     * Calls ActiveForm::validate() on the model if current request is ajax and not pjax.
     * @param ActiveRecord|array $model
     * @return Response returns boolean false if current request is not ajax or is pjax
     */
    protected function validateAjax($model)
    {
        if (!Yii::$app->request->isAjax || Yii::$app->request->isPjax) {
            return false;
        }
        $response = clone Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        if (!is_array($model)) {
            $model = [$model];
        }
        $response->content = json_encode(call_user_func_array('\yii\widgets\ActiveForm::validate', $model));
        return $response;
    }

    /**
     * Preliminary check if model can be saved.
     * @param ActiveRecord $model
     * @return bool
     */
    protected function beforeSave($model)
    {
        return $model->validate();
    }

    /**
     * Saves the model and its relations.
     * @param ActiveRecord $model
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    protected function save($model)
    {
        /** @var \nineinchnick\audit\behaviors\TrackableBehavior $trackable */
        if (($trackable = $model->getBehavior('trackable')) !== null) {
            $model->beginChangeset();
        }
        $result = $model->save(false) && $model->saveRelations(Yii::$app->getRequest()->getBodyParams());
        if ($trackable !== null) {
            $model->endChangeset();
        }
        return $result;
    }

    /**
     * Sets a flash message and configures response headers.
     * @param ActiveRecord $model
     * @param bool $wasNew
     */
    protected function afterSave($model, $wasNew)
    {
        if ($wasNew) {
            $message = Yii::t('app', '<strong>Success!</strong> A new record has been created.');
        } else {
            $message = Yii::t('app', '<strong>Success!</strong> Record has been updated.');
        }

        if (!isset($_POST[self::NEW_RELATED_BUTTON_NAME])) {
            $this->setFlash('success', $message);
        }

        $id = $this->exportKey($model->getPrimaryKey(true));
        $response = Yii::$app->getResponse();
        $response->setStatusCode(201);

        if (!Yii::$app->request->isAjax) {
            // when such header is set, ActiveController will change the response code set above to a redirect
            $response->getHeaders()->set('Location', isset($_POST[self::NEW_RELATED_BUTTON_NAME])
                ? Url::current(['id' => $id, self::ADD_RELATED_NAME => $_POST[self::NEW_RELATED_BUTTON_NAME]], true)
                : Url::toRoute([$this->viewAction, 'id' => $id], true));
        }

        $response->getHeaders()->set('X-Primary-Key', $id);
    }

    /**
     * Prepares response params, like fields and relations.
     * @param ActiveRecord $model
     * @return array
     */
    protected function getResponse($model)
    {
        $hiddenAttributes = array_filter(explode(',', Yii::$app->getRequest()->getQueryParam('hide', '')));
        $fields = FormBuilder::getFormFields($model, $this->getFields($model, 'form'), false, $hiddenAttributes);

        return [
            'model' => $model,
            'fields' => empty($fields) ? [] : [$fields],
            'relations' => $this->getModelRelations($model, $this->getExtraFields($model)),
        ];
    }

    /**
     * @param array $relation an item obtained from getModelRelations() result array,
     * @param array $selection must contain 'add' and 'remove' keys with array of keys (single or composite)
     * @return array relation with modified query in the dataProvider object
     */
    private function addRelationSelection($relation, $selection)
    {
        /** @var ActiveRecord $relatedModel */
        $relatedModel = $relation['model'];
        $relatedPk = $relatedModel::primaryKey();
        /** @var \yii\db\ActiveQuery $query */
        $query = $relation['dataProvider']->query;

        $conditions = ['or'];
        $fkCondition = [
            'in',
            array_keys($query->link),
            array_combine(array_values($query->link), $query->primaryModel->getPrimaryKey(true)),
        ];
        if (!empty($selection['add'])) {
            $conditions[] = ['in', $relatedPk, self::importKey($relatedPk, $selection['add'])];
        }
        if (!empty($selection['remove'])) {
            $conditions[] = [
                'and',
                $fkCondition,
                ['not in', $relatedPk, self::importKey($relatedPk, $selection['remove'])]
            ];
        } else {
            $conditions[] = $fkCondition;
        }
        if ($conditions !== ['or']) {
            $query->andWhere($conditions);
            $query->primaryModel = null;
        }
        return $relation;
    }

    /**
     * @param string $relationName
     * @param array $relation an item obtained from getModelRelations() result array,
     * @param \netis\crud\db\ActiveRecord $model
     * @return array
     */
    private function getRelationButtons($relationName, $relation, $model)
    {
        /** @var \netis\crud\db\ActiveRecord $relatedModel */
        $relatedModel = $relation['model'];
        $dataProvider = $relation['dataProvider'];

        list($createRoute, $searchRoute, $indexRoute) = FormBuilder::getRelationRoutes(
            $model,
            $relatedModel,
            $dataProvider->query
        );

        $result = [];
        if ($createRoute !== null) {
            $result[self::CREATE_RELATED_BUTTON] = Html::a('<span class="glyphicon glyphicon-file"></span>', '#', [
                'title'         => Yii::t('app', 'Create new'),
                'aria-label'    => Yii::t('app', 'Create new'),
                'data-pjax'     => '0',
                'data-toggle'   => 'modal',
                'data-target'   => '#relationModal',
                'data-relation' => $relationName,
                'data-title'    => $relatedModel->getCrudLabel('create'),
                'data-pjax-url' => Url::toRoute($createRoute),
                'data-mode'     => FormBuilder::MODAL_MODE_NEW_RECORD,
                'class'         => 'btn btn-default',
                'id'            => 'createRelation-' . $relationName,
            ]);
        } else {
            // a normal submit button that tries to save the record
            // and open the usual modal immediately after reloading the page
            $result[self::CREATE_RELATED_BUTTON] = Html::button('<span class="glyphicon glyphicon-file"></span>', [
                'name'  => self::NEW_RELATED_BUTTON_NAME,
                'type'  => 'submit',
                'class' => 'btn btn-default',
                'value' => $relationName,
            ]);
        }

        if ($searchRoute !== null) {
            $result[self::SEARCH_RELATED_BUTTON] = Html::a('<span class="glyphicon glyphicon-plus"></span>', '#', [
                'title'         => Yii::t('app', 'Add existing'),
                'aria-label'    => Yii::t('app', 'Add existing'),
                'data-pjax'     => '0',
                'data-toggle'   => 'modal',
                'data-target'   => '#relationModal',
                'data-relation' => $relationName,
                'data-title'    => $relatedModel->getCrudLabel('index'),
                'data-pjax-url' => Url::toRoute($searchRoute),
                'data-mode'     => FormBuilder::MODAL_MODE_EXISTING_RECORD,
                'class'         => 'btn btn-default',
            ]);
        }

        return $result;
    }

    /**
     * Adds query conditions to include related models selection.
     * Adds buttons definition to relation data.
     * @inheritdoc
     */
    public function getModelRelations($model, $extraFields)
    {
        $relations = parent::getModelRelations($model, $extraFields);
        if (($relationName = Yii::$app->request->getQueryParam('_pjax')) !== null
            && ($relationName = substr($relationName, 1, -4)) !== ''
            && isset($relations[$relationName])
        ) {
            $headers = Yii::$app->request->getHeaders();
            $selection = [
                'add' => self::explodeEscaped(self::KEYS_SEPARATOR, $headers->get('X-Selection-add')),
                'remove' => self::explodeEscaped(self::KEYS_SEPARATOR, $headers->get('X-Selection-remove')),
            ];
            $relations[$relationName] = $this->addRelationSelection(
                $relations[$relationName],
                $selection
            );
        }
        foreach ($relations as $relationName => &$relation) {
            $relation['buttons'] = $this->getRelationButtons($relationName, $relation, $model);
        }
        return $relations;
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param string $fields
     * @param string $relationName
     * @param \yii\db\ActiveQuery $relation
     * @return array grid columns
     */
    public static function getRelationGridColumns($model, $fields, $relationName, $relation)
    {
        $columns = parent::getRelationGridColumns($model, $fields, $relationName, $relation);
        $controller = Yii::$app->crudModelsMap[$model::className()];
        $actionColumn = new \yii\grid\ActionColumn();

        if (!isset($columns[0]) || !isset($columns[0]['class']) || $columns[0]['class'] !== 'yii\grid\ActionColumn') {
            array_unshift($columns, [
                'class'         => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
                'controller'    => $controller,
                'template'      => '',
                'buttons'       => [],
            ]);
        }

        $columns[0]['template'] = '{update} {unlink}';
        $columns[0]['urlCreator'] = function ($action, $model, $key, $index) use ($controller, $relation) {
            $params = is_array($key) ? $key : ['id' => (string)$key];
            if ($action === 'update') {
                $params['hide'] = implode(',', array_keys($relation->link));
            }
            $params[0] = $controller . '/' . $action;

            return Url::toRoute($params);
        };
        $columns[0]['buttons']['update'] = function ($url, $model, $key) use ($actionColumn, $relationName) {
            /** @var \netis\crud\db\ActiveRecord $model */
            if (!Yii::$app->user->can($model::className() . '.update', ['model' => $model])) {
                return null;
            }

            $options = array_merge([
                'title'         => Yii::t('app', 'Update'),
                'aria-label'    => Yii::t('app', 'Update'),
                'data-pjax'     => '0',
                // required for editing in a modal
                'data-toggle'   => 'modal',
                'data-target'   => '#relationModal',
                'data-relation' => $relationName,
                'data-title'    => $model->getCrudLabel('update'),
                'data-pjax-url' => $url,
                'class'         => 'btn btn-default btn-xs',
            ], $actionColumn->buttonOptions);
            return Html::a('<span class="glyphicon glyphicon-pencil"></span>', $url, $options);
        };

        /** @var \yii\db\ActiveQuery $relation */
        $remove = false;
        $modelClass = $relation->modelClass;
        foreach (array_keys($relation->link) as $foreignKey) {
            $remove = $remove || !$modelClass::getTableSchema()->getColumn($foreignKey)->allowNull;
        }

        $columns[0]['buttons']['unlink'] = function ($url, $model, $key) use ($actionColumn, $remove) {
            /** @var \netis\crud\db\ActiveRecord $model */
            if (!Yii::$app->user->can($model::className() . ($remove ? '.delete' : '.update'), ['model' => $model])) {
                return null;
            }

            $options = array_merge([
                'title'      => Yii::t('app', 'Unlink'),
                'aria-label' => Yii::t('app', 'Unlink'),
                //'data-confirm' => Yii::t('app', 'Are you sure you want to unlink this item?'),
                'data-pjax'  => '0',
                'class'      => 'remove btn btn-default btn-xs',
            ], $actionColumn->buttonOptions);
            return Html::a('<span class="glyphicon glyphicon-remove"></span>', $url, $options);
        };
        return $columns;
    }

    /**
     * Changed format from crudLink to text.
     * @inheritdoc
     */
    protected static function getRelationColumn($model, $field, $relation)
    {
        return [
            'attribute' => $field,
            'format'    => 'text',
            'visible'   => true,
            'label'     => $model->getRelationLabel($relation, $field),
        ];
    }
}
