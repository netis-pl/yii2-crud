<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $dataProvider yii\data\ActiveDataProvider */

$searchModel = $this->context->getSearchModel();
$this->title = $searchModel->getCrudLabel();
$this->params['breadcrumbs'] = $this->context->getBreadcrumbs($this->context->action, null, $searchModel);
?>
<div class="ar-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= \yii\widgets\Menu::widget([
            'items' => $this->context->getMenu($this->context->action, $searchModel),
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