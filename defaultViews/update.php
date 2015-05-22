<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $model yii\db\ActiveRecord */

$searchModel = $this->context->getSearchModel();
$this->title = $searchModel->getCrudLabel($model->isNewRecord ? 'create' : 'update');
if ($model->isNewRecord) {
    $this->title .= ': ' . $model->__toString();
}
$this->params['breadcrumbs'][] = ['label' => $searchModel->getCrudLabel('index'), 'url' => ['index']];
if (!$model->isNewRecord) {
    $this->params['breadcrumbs'][] = ['label' => $model->__toString(), 'url' => ['view', 'id' => $model->primaryKey]];
}
$this->params['breadcrumbs'][] = $model->isNewRecord ? $this->title : Yii::t('app', 'Update');
?>
<div class="ar-<?= $model->isNewRecord ? 'create' : 'update'; ?>">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'searchModel' => $searchModel,
        'model' => $model,
    ]) ?>

</div>