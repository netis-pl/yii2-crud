<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use netis\utils\widgets\FormBuilder;
use \netis\utils\crud\UpdateAction;

/* @var $this \netis\utils\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var $form yii\widgets\ActiveForm */
/* @var $controller netis\utils\crud\ActiveController */
/* @var $action netis\utils\crud\UpdateAction */
/* @var $view \netis\utils\web\View */
/* @var $formOptions array form options, will be merged with defaults */
/* @var $buttons array */
/* @var $formBody string if set, allows to override only the form part */
/* @var $defaultWidth integer default form column width */

$controller = $this->context;
$action = $controller->action;
$view = $this;

if (!isset($defaultWidth)) {
    $defaultWidth = Yii::$app->request->getIsAjax() ? 12 : 4;
}

if (!isset($buttons)) {
    $buttons = [
        Html::submitButton(
            $model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'),
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']
        ),
    ];
}

FormBuilder::registerSelect($this);
//do not render modal for relations handling if request is pjax and content will be inserted in modal.
if (($pjax = Yii::$app->request->getQueryParam('_pjax')) === null || $pjax !== '#relationModal .modal-body') {
    echo FormBuilder::registerRelations($this);
}
?>

<div class="ar-form">
    <?php $form = isset($form) ? $form : ActiveForm::begin(array_merge([
        'enableAjaxValidation' => !Yii::$app->request->getIsAjax(),
        'validateOnSubmit' => true,
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
    ], isset($formOptions) ? $formOptions : [])); ?>

    <?php if (FormBuilder::hasRequiredFields($model, $fields)):?>
    <p class="note">
        <?= Yii::t('app', 'Fields with {asterisk} are required.', [
            'asterisk' => '<span class="required">*</span>'
        ]); ?>
    </p>
    <?php endif;?>

    <?= $form->errorSummary($model); ?>

    <?php if (!empty($fields)): ?>
        <fieldset class="well">
            <?= isset($formBody) ? $formBody : FormBuilder::renderRow($this, $model, $form, $fields, $defaultWidth); ?>
        </fieldset>
    <?php endif; ?>

    <?= $this->render('_relations', [
        'model' => $model,
        'relations' => $relations,
    ], $this->context) ?>

    <div class="form-group form-buttons">
        <?= implode("\n        ", $buttons); ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php if (isset($_GET[UpdateAction::ADD_RELATED_NAME])) {
    $this->registerJs(
        '$("#createRelation-' . $_GET[UpdateAction::ADD_RELATED_NAME] . '").click();'
    );
} ?>