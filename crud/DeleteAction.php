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

        if ($model->delete() === false) {
            throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
        }

        $response = Yii::$app->getResponse();
        $response->setStatusCode(204);

        $message = Yii::t('app', 'Record has been successfully deleted.');
        $this->setFlash('success', $message);

        $response->getHeaders()->set('Location', Url::toRoute([$this->indexAction], true));
    }
}
