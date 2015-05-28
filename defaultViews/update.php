<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$this->title = $model->getCrudLabel($model->isNewRecord ? 'create' : 'update');
if (!$model->isNewRecord) {
    $this->title .= ': ' . $model->__toString();
}
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);

// skip the whole view if pjax requested specific part
if (($relationName = Yii::$app->request->getQueryParam('_pjax')) !== null
    && ($relationName = substr($relationName, 1)) !== ''
    && isset($relations[$relationName])
) {
    echo $this->render('_relation_edit_widget', [
        'model' => $relations[$relationName]['model'],
        'relations' => $relations,
        'relationName' => $relationName,
    ]);
    return;
}
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= $this->render('_form', [
    'model' => $model,
    'fields' => $fields,
    'relations' => $relations,
]) ?>

