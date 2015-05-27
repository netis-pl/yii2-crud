<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $attributes array */
/* @var $relations array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$this->title = $model->__toString();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => $attributes,
])
?>
<?php
foreach ($relations as $relationName => $data) {
    echo $this->render('_relation_widget', array(
        'model' => $model,
        'relations' => $relations,
        'relationName' => $relationName,
    ));
}
?>
