<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$searchModel = $controller->getSearchModel();
$this->title = $searchModel->getCrudLabel();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, null, $searchModel);
?>
<div class="ar-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= \yii\widgets\Menu::widget([
            'items' => $controller->getMenu($controller->action, $searchModel),
            'itemOptions' => [
                'class' => 'btn btn-default',
            ],
        ]) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        //'filterModel' => $searchModel,
        'columns' => $searchModel->getColumns(),
    ]); ?>

</div>