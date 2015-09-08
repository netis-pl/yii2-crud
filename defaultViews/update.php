<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model netis\utils\crud\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;

if (($pjax = Yii::$app->request->getQueryParam('_pjax')) !== null) {
    if ($pjax === '#relationModal .modal-body') {
        $relations = [];
    } else {
        // optimization: render only the relation widget instead of the whole form
        $relationName = substr($pjax, 1, -4);

        echo $this->render('_relation_widget', [
            'model' => $model,
            'relations' => $relations,
            'relationName' => $relationName,
            'isActive' => true,
        ], $this->context);

        return;
    }
}

$this->title = $model->getCrudLabel($model->isNewRecord ? 'create' : 'update');
if (!$model->isNewRecord) {
    $this->title .= ': ' . $model->__toString();
}
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= $this->render('_form', [
    'model' => $model,
    'fields' => $fields,
    'relations' => $relations,
], $this->context) ?>

