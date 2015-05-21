<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $model yii\db\ActiveRecord */

$this->title = $model->__toString();
$this->params['breadcrumbs'][] = ['label' => $searchModel->getCrudLabel('index'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ar-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a($searchModel->getCrudLabel('update'), ['update', 'id' => $model->primaryKey], ['class' => 'btn btn-primary']) ?>
        <?= Html::a($searchModel->getCrudLabel('delete'), ['delete', 'id' => $model->primaryKey], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => $searchModel->getDetailAttributes(),
    ]) ?>

</div>