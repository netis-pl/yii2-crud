<?php

use netis\utils\web\FormBuilder;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model netis\utils\db\ActiveSearchTrait */
/* @var $fields array */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="ar-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <fieldset>
        <?php FormBuilder::renderRow($this, $model, $form, $fields, Yii::$app->request->getIsAjax() ? 12 : 6); ?>
    </fieldset>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Search'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Yii::t('app', 'Reset'), ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>