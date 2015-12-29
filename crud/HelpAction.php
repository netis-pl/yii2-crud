<?php

namespace netis\crud\crud;

use netis\crud\web\Response;
use Yii;

/**
 * Description here...
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
class HelpAction extends \yii\web\ViewAction
{
    /**
     * Adds menu and breadcrumbs. Required, because help views are rendered from markdown templates and can't
     * execute any logic.
     * @param ActionEvent $event
     * @return bool
     */
    public function beforeRun()
    {
        /** @var ActiveController $controller */
        $controller = $this->controller;

        $model = new $controller->modelClass();
        $format = Yii::$app->response->format;
        if ($format !== Response::FORMAT_HTML && $format !== Response::FORMAT_PDF) {
            return true;
        }
        if ($model instanceof \netis\crud\db\ActiveRecord) {
            if ($controller->view->title === null) {
                $controller->view->title = $model->getCrudLabel('relation') . ' - ' . Yii::t('app', 'Help');
            }
            $controller->view->params['breadcrumbs'] = $controller->getBreadcrumbs($this, $model);
            $controller->view->params['menu'] = $controller->getMenu($this, $model);
        } else {
            $controller->view->title = Yii::t('app', 'Help');
        }
        return true;
    }
}