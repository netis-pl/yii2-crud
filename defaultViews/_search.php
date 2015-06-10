<?php

use netis\utils\widgets\FormBuilder;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model netis\utils\db\ActiveSearchTrait */
/* @var $fields array */
/* @var $form yii\widgets\ActiveForm */

FormBuilder::registerSelect($this);

// split fields into four columns
$sourceFields = $fields;
$columnsNumber = 5;
$size = ceil(count($sourceFields) / (double)$columnsNumber);
$fields = [];
for ($i = 0; $i < $columnsNumber; $i++) {
    $fields[] = array_slice($sourceFields, $i * $size, $size);
}
?>

<div class="ar-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <fieldset>
        <?php FormBuilder::renderRow($this, $model, $form, $fields, 10); ?>
    </fieldset>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Search'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Yii::t('app', 'Reset'), ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>