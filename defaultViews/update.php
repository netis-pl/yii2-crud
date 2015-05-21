<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $model yii\db\ActiveRecord */

$this->title = $searchModel->getCrudLabel('update') . ': ' . $model->__toString();
$this->params['breadcrumbs'][] = ['label' => $searchModel->getCrudLabel('index'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->__toString(), 'url' => ['view', 'id' => $model->primaryKey]];
$this->params['breadcrumbs'][] = Yii::t('app', 'Update');
?>
<div class="ar-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'searchModel' => $searchModel,
        'model' => $model,
    ]) ?>

</div>