<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\helpers\Url;
use yii\web\ServerErrorHttpException;

class DeleteAction extends Action
{
    /**
     * @var string the name of the index action. This property is need to create the URL
     * when the model is successfully deleted.
     */
    public $indexAction = 'index';

    /**
     * Deletes a model.
     * @param mixed $id id of the model to be deleted.
     * @throws ServerErrorHttpException on failure.
     */
    public function run($id)
    {
        $model = $this->findModel($id);

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }

        /** @var \nineinchnick\audit\behaviors\TrackableBehavior $trackable */
        if (($trackable = $model->getBehavior('trackable')) !== null) {
            $trackable->beginChangeset();
        }
        if ($model->delete() === false) {
            throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
        }
        if ($trackable !== null) {
            $trackable->endChangeset();
        }

        $response = Yii::$app->getResponse();
        $response->setStatusCode(204);

        $message = Yii::t('app', '<strong>Success!</strong> Record has been deleted.');
        $this->setFlash('success', $message);

        $response->getHeaders()->set('Location', Url::toRoute([$this->indexAction], true));
    }
}
