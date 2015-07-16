<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\Json;
use netis\utils\widgets\FormBuilder;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var $form yii\widgets\ActiveForm */
/* @var $controller netis\utils\crud\ActiveController */
/* @var $action netis\utils\crud\UpdateAction */
/* @var $view \netis\utils\web\View */
/* @var $formOptions array form options, will be merged with defaults */
/* @var $buttons array */

$controller = $this->context;
$action = $controller->action;
$view = $this;

if (!isset($buttons)) {
    $buttons = [
        Html::submitButton(
            $model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'),
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']
        ),
    ];
}

FormBuilder::registerSelect($this);
echo FormBuilder::registerRelations($this);
?>

<div class="ar-form">
    <?php $form = ActiveForm::begin(array_merge([
        'enableAjaxValidation' => !Yii::$app->request->getIsAjax(),
        'validateOnSubmit' => true,
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
    ], isset($formOptions) ? $formOptions : [])); ?>

    <p class="note">
        <?= Yii::t('app', 'Fields with {asterisk} are required.', [
            'asterisk' => '<span class="required">*</span>'
        ]); ?>
    </p>

    <?= $form->errorSummary($model); ?>

    <fieldset class="well">
    <?php FormBuilder::renderRow($this, $model, $form, $fields, Yii::$app->request->getIsAjax() ? 12 : 4); ?>
    </fieldset>

    <?= $this->render('_relations', [
        'model' => $model,
        'relations' => $relations,
        'form' => $form,
    ], $this->context) ?>

    <div class="form-group form-buttons">
        <?= implode("\n        ", $buttons); ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
