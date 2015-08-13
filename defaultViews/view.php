<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this \netis\utils\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $attributes array */
/* @var $relations array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$this->title = $model->getCrudLabel('read').': '.$model->__toString();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);

// skip the whole view if pjax requested specific part
if (($relationName = Yii::$app->request->getQueryParam('_pjax')) !== null
    && ($relationName = substr($relationName, 1)) !== ''
    && isset($relations[$relationName])
) {
    echo $this->render('_relation_widget', [
        'model' => $relations[$relationName]['model'],
        'relations' => $relations,
        'relationName' => $relationName,
    ]);
    return;
}

?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => $attributes,
]) ?>

<?= $this->render('_relations', [
    'model' => $model,
    'relations' => $relations,
], $this->context) ?>

