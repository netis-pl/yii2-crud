<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="ar-form">

    <?php $form = ActiveForm::begin([
        'enableAjaxValidation' => true,
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
    ]); ?>

<?php foreach ($model->attributes() as $attribute): ?>
    <?= $form->field($model, $attribute)->textInput() ?>
<?php endforeach; ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'), [
            'class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary',
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>