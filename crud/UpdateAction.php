<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\widgets\FormBuilder;
use Yii;
use yii\base\Model;
use yii\helpers\Url;
use yii\web\Request;
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

        if ($this->load($model, Yii::$app->getRequest())) {
            if (($response = $this->validateAjax($model)) !== false) {
                return $response;
            }
            if ($this->beforeSave($model)) {
                $trx = $model->getDb()->beginTransaction();
                if (!$this->save($model)) {
                    throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
                }
                $trx->commit();

                $this->afterSave($model, $wasNew);
            }
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
        } else {
            $model = $this->findModel($id);
            $model->scenario = $this->updateScenario;
        }

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
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
            $trackable->beginChangeset();
        }
        $result = $model->save(false) && $model->saveRelations(Yii::$app->getRequest()->getBodyParams());
        if ($trackable !== null) {
            $trackable->endChangeset();
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
            $message = Yii::t('app', 'A new has been successfully created.');
        } else {
            $message = Yii::t('app', 'Record has been successfully updated.');
        }
        $this->setFlash('success', $message);

        $id = $this->exportKey($model->getPrimaryKey(true));
        $response = Yii::$app->getResponse();
        $response->setStatusCode(201);
        $response->getHeaders()->set('Location', Url::toRoute([$this->viewAction, 'id' => $id], true));
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

        return [
            'model' => $model,
            'fields' => FormBuilder::getFormFields($model, $this->getFields($model, 'form'), false, $hiddenAttributes),
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
        /** @var \yii\db\ActiveQuery $query */
        $query = $relation['dataProvider']->query;

        $conditions = ['or'];
        $fkCondition = [
            'in',
            array_keys($query->link),
            array_combine(array_values($query->link), $query->primaryModel->getPrimaryKey(true)),
        ];
        if (!empty($selection['add'])) {
            $conditions[] = ['in', $relatedModel::primaryKey(), self::importKey($relatedModel, $selection['add'])];
        }
        if (!empty($selection['remove'])) {
            $conditions[] = [
                'and',
                $fkCondition,
                ['not in', $relatedModel::primaryKey(), self::importKey($relatedModel, $selection['remove'])]
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
     * @param ActiveRecord $model
     * @return array
     */
    private function getRelationButtons($relationName, $relation, $model)
    {
        /** @var \yii\db\ActiveRecord $relatedModel */
        $relatedModel = $relation['model'];
        if (($route = Yii::$app->crudModelsMap[$relatedModel::className()]) === null) {
            return [];
        }
        $dataProvider = $relation['dataProvider'];

        //! @todo enable this route only if current record is not new or related model can have null fk
        $createRoute = Url::toRoute([
            $route . '/update',
            'hide' => implode(',', array_keys($dataProvider->query->link)),
            $relatedModel->formName() => array_combine(
                array_keys($dataProvider->query->link),
                $model->getPrimaryKey(true)
            ),
        ]);

        $parts = explode('\\', $relatedModel::className());
        $relatedModelClass = array_pop($parts);
        $relatedSearchModelClass = implode('\\', $parts) . '\\search\\' . $relatedModelClass;
        $searchRoute = !class_exists($relatedSearchModelClass) ? null : Url::toRoute([
            $route . '/relation',
            'per-page' => 10,
            'relation' => $dataProvider->query->inverseOf,
            'id'       => Action::exportKey($model->getPrimaryKey()),
        ]);

        $result = [
            \yii\helpers\Html::a('<span class="glyphicon glyphicon-file"></span>', '#', [
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
        ];

        if ($searchRoute !== null) {
            $result[] = \yii\helpers\Html::a('<span class="glyphicon glyphicon-plus"></span>', '#', [
                'title'         => Yii::t('app', 'Add existing'),
                'aria-label'    => Yii::t('app', 'Add existing'),
                'data-pjax'     => '0',
                'data-toggle'   => 'modal',
                'data-target'   => '#relationModal',
                'data-relation' => $relationName,
                'data-title'    => $relatedModel->getCrudLabel('index'),
                'data-pjax-url' => $searchRoute,
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
     * @param string $inverseRelation
     * @return array grid columns
     */
    public static function getRelationGridColumns($model, $inverseRelation)
    {
        $columns = parent::getRelationGridColumns($model, $inverseRelation);
        if (!isset($columns[0]) || !isset($columns[0]['class']) || $columns[0]['class'] !== 'yii\grid\ActionColumn') {
            return $columns;
        }
        $actionColumn = new \yii\grid\ActionColumn();
        $columns[0]['template'] = '{view} {unlink}';
        $columns[0]['buttons']['unlink'] = function ($url, $model, $key) use ($actionColumn) {
            if (!Yii::$app->user->can($model::className() . '.read')) {
                return null;
            }

            $options = array_merge([
                'title' => Yii::t('app', 'Unlink'),
                'aria-label' => Yii::t('app', 'Unlink'),
                //'data-confirm' => Yii::t('app', 'Are you sure you want to unlink this item?'),
                'data-pjax' => '0',
                'class' => 'remove',
            ], $actionColumn->buttonOptions);
            return \yii\helpers\Html::a('<span class="glyphicon glyphicon-remove"></span>', $url, $options);
        };
        return $columns;
    }
}
