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
            $model = new $this->modelClass(['scenario' => $this->scenario]);
        } else {
            $model = $this->findModel($id);
            $model->scenario = $this->scenario;
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
        return $model->save(false) && $model->saveRelations(Yii::$app->getRequest()->getBodyParams());
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
        $response->getHeaders()->set('X-Primary-key', $id);
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
     * When the request is pjax, use the selection query param.
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
            /** @var ActiveRecord $relatedModel */
            $relatedModel = $relations[$relationName]['model'];
            /** @var \yii\db\ActiveQuery $query */
            $query = $relations[$relationName]['dataProvider']->query;

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
