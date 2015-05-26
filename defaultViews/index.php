<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$searchModel = $controller->getSearchModel();
$this->title = $model->getCrudLabel();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, null);
$this->params['menu'] = $controller->getMenu($controller->action, $searchModel);
?>

<h1><?= Html::encode($this->title) ?></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    //'filterModel' => $searchModel,
    'columns' => $columns,
]); ?>
