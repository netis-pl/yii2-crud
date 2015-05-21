<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $model yii\db\ActiveRecord */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="ar-form">

    <?php $form = ActiveForm::begin(); ?>

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