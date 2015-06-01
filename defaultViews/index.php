<?php

use yii\helpers\Html;
use netis\utils\widgets\GridView;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$searchModel = $controller->getSearchModel();
$this->title = $searchModel->getCrudLabel();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
$this->params['menu'] = $controller->getMenu($controller->action, $searchModel);
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    //'filterModel' => $searchModel,
    'columns' => $columns,
    'layout' => '{lengthPicker}{items}{summary}{pager}',
]); ?>
