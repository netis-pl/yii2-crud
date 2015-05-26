<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Model;
use yii\helpers\Url;
use yii\web\ServerErrorHttpException;

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

        return $model;
    }
}
