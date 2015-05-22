<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $model yii\db\ActiveRecord */

$searchModel = $this->context->getSearchModel();
$this->title = $searchModel->getCrudLabel($model->isNewRecord ? 'create' : 'update');
if (!$model->isNewRecord) {
    $this->title .= ': ' . $model->__toString();
}
$this->params['breadcrumbs'] = $this->context->getBreadcrumbs($this->context->action, $model, $searchModel);
?>
<div class="ar-<?= $model->isNewRecord ? 'create' : 'update'; ?>">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= \yii\widgets\Menu::widget([
            'items' => $this->context->getMenu($this->context->action, $searchModel),
            'itemOptions' => [
                'class' => 'btn btn-default',
            ],
        ]) ?>
    </p>

    <?= $this->render('_form', [
        'searchModel' => $searchModel,
        'model' => $model,
    ]) ?>

</div>