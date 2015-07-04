<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\helpers\Url;
use yii\web\ServerErrorHttpException;

class ToggleAction extends Action
{
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
    public function run($id, $enable = null)
    {

        $model = $this->findModel($id);

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }

        if ($enable !== null) {
            $enable = (bool)$enable;
        }
        /** @var \nineinchnick\audit\behaviors\TrackableBehavior $trackable */
        if (($trackable = $model->getBehavior('trackable')) !== null) {
            $trackable->beginChangeset();
        }
        $model->toggle($enable);
        if ($trackable !== null) {
            $trackable->endChangeset();
        }

        $response = Yii::$app->getResponse();
        $response->setStatusCode($enable ? 205 : 204);

        if ($enable) {
            $message = Yii::t('app', 'Record has been successfully restored.');
        } else {
            $message = Yii::t('app', 'Record has been successfully disabled.');
        }
        $this->setFlash('success', $message);

        $id = $this->exportKey($model->getPrimaryKey(true));
        $response->getHeaders()->set('Location', Url::toRoute([$this->viewAction, 'id' => $id], true));
    }
}
