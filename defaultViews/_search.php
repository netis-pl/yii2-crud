<?php

use netis\utils\widgets\FormBuilder;
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $model netis\utils\db\ActiveSearchInterface */
/* @var $fields array */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $formBody string if set, allows to override only the form part */

FormBuilder::registerSelect($this);
echo FormBuilder::registerRelations($this);

// split fields into four columns
$sourceFields = $fields;
$columnsNumber = 5;
$size = ceil(count($sourceFields) / (double)$columnsNumber);
$fields = [];
for ($i = 0; $i < $columnsNumber; $i++) {
    $fields[] = array_slice($sourceFields, $i * $size, $size);
}

$visible = array_filter($model->getAttributes()) !== [];
?>

<div id="advancedSearch" class="collapse <?= $visible ? 'in' : '' ?> ar-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <fieldset>
        <?= isset($formBody) ? $formBody : FormBuilder::renderRow($this, $model, $form, $fields, 10); ?>
    </fieldset>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Search'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Yii::t('app', 'Reset'), ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
