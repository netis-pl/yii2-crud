<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\Json;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var $form yii\widgets\ActiveForm */
/* @var $controller netis\utils\crud\ActiveController */
/* @var $action netis\utils\crud\UpdateAction */
/* @var $view \netis\utils\web\View */

$controller = $this->context;
$action = $controller->action;
$view = $this;

// init relation tools used in _relations subview
// relations modal may contain a form and must be rendered outside ActiveForm
echo \yii\bootstrap\Modal::widget([
    'id' => 'relationModal',
    'size' => \yii\bootstrap\Modal::SIZE_LARGE,
    'header' => '<span class="modal-title"></span>',
    'footer' => implode('', [
        Html::button(Yii::t('app', 'Save'), [
            'id' => 'relationSave',
            'class' => 'btn btn-primary',
        ]),
        Html::button(Yii::t('app', 'Cancel'), [
            'class' => 'btn btn-default',
            'data-dismiss' => 'modal',
            'aria-hidden' => 'true',
        ]),
    ]),
]);

\netis\utils\assets\RelationsAsset::register($this);
$options = Json::htmlEncode([
    'i18n' => [
        'loadingText' => Yii::t('app', 'Loading, please wait.'),
    ],
    'keysSeparator' => \netis\utils\crud\Action::KEYS_SEPARATOR,
    'compositeKeySeparator' => \netis\utils\crud\Action::COMPOSITE_KEY_SEPARATOR,
]);
$this->registerJs("netis.init($options)");
?>

<div class="ar-form">
    <?php $form = ActiveForm::begin([
        'enableAjaxValidation' => true,
        'validateOnSubmit' => true,
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
    ]); ?>

    <p class="note">
        <?= Yii::t('app', 'Fields with {asterisk} are required.', [
            'asterisk' => '<span class="required">*</span>'
        ]); ?>
    </p>

    <?= $form->errorSummary($model); ?>

    <fieldset>
    <?php $action->renderRow($this, $model, $form, [$fields], Yii::$app->request->getIsAjax() ? 12 : 6); ?>
    </fieldset>

    <?= $this->render('_relations', [
        'model' => $model,
        'relations' => $relations,
        'form' => $form,
    ]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'), [
            'class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary',
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>