<?php

use yii\base\Model;
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var $form yii\widgets\ActiveForm */
/* @var $controller netis\utils\crud\ActiveController */
/* @var $view \netis\utils\web\View */

$controller = $this->context;
$view = $this;
$maxColumnWidth = Yii::$app->request->getIsAjax() ? 12 : 6;

$renderControlGroup = function ($name, $data, $form) use ($controller, $model) {
    /** @var \netis\utils\crud\ActiveController $controller */
    /** @var Model $model */
    /** @var ActiveForm $form */
    if (isset($data['model'])) {
        $model = $data['model'];
    }
    $field = $form->field($model, $data['attribute']);
    if (isset($data['formMethod'])) {
        if (is_string($data['formMethod'])) {
            echo call_user_func([$field, $data['formMethod']], $data['options']);
        } else {
            echo call_user_func($data['formMethod'], $field, $data['options']);
        }
        return;
    }
    if (isset($data['options']['label'])) {
        $label = $data['options']['label'];
        unset($data['options']['label']);
    } else {
        $label = $model->getAttributeLabel($name);
    }
    $errorClass = $model->getErrors($data['attribute']) !== [] ? 'error' : '';
?>
        <div class="form-group  <?= $errorClass ?>">
            <?= $field->label(['class' => 'control-label', 'label' => $label]); ?>
            <div>
                <?= $field->widget($data['widgetClass'], $data['options']); ?>
                <?= $field->error(['class' => 'help-block']); ?>
            </div>
        </div>
<?php
    return;
};
$renderRow = function ($renderControlGroup, $fields, $form, $topColumnWidth = 12) use (&$renderRow) {
    if (empty($fields)) {
        return;
    }
    $oneColumn = count($fields) == 1;
    echo $oneColumn ? '' : '<div class="row">';
    $columnWidth = ceil($topColumnWidth / count($fields));
    foreach ($fields as $name => $column) {
        echo $oneColumn ? '' : '<div class="col-lg-' . $columnWidth . '">';
        if (is_string($column)) {
            echo $column;
        } elseif (!is_numeric($name) && isset($column['attribute'])) {
            $renderControlGroup($name, $column, $form);
        } else {
            foreach ($column as $name2 => $row) {
                if (is_string($row)) {
                    echo $row;
                } elseif (!is_numeric($name2) && isset($row['attribute'])) {
                    $renderControlGroup($name2, $row, $form);
                } else {
                    $renderRow($renderControlGroup, $row, $form);
                }
            }
        }
        echo $oneColumn ? '' : '</div>';
    }
    echo $oneColumn ? '' : '</div>';
};


$pjax = Yii::$app->request->getQueryParam('_pjax');
$activeRelation = false;
foreach ($relations as $relationName => $data) {
    if ($pjax === null || $pjax === "#$relationName") {
        $activeRelation = $relationName;
        break;
    }
}
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
    <?php $renderRow($renderControlGroup, [$fields], $form, $maxColumnWidth); ?>
    </fieldset>

    <div role="tabpanel" class="relations-panel">
        <ul class="nav nav-tabs" role="tablist">
<?php foreach ($relations as $relationName => $data): ?>
            <li role="presentation"
                class="<?= $relationName === $activeRelation ? 'active' : ''?>">
                <a href="#tab_<?= $relationName ?>" aria-controls="tab_<?= $relationName ?>"
                   role="tab" data-toggle="tab">
                    <?= $data['model']->getCrudLabel('relation') ?>
                </a>
            </li>
<?php endforeach; ?>
        </ul>
        <div class="tab-content">
<?php
foreach ($relations as $relationName => $data) {
    echo $this->render('_relation_edit_widget', [
        'model' => $model,
        'relations' => $relations,
        'relationName' => $relationName,
        'isActive' => $relationName === $activeRelation,
    ]);
}
?>
        </div>
    </div>

    <!--div class="panel-group" id="relationsAccordion" role="tablist" aria-multiselectable="true">
<?php
/*foreach ($relations as $relationName => $data) {
    echo $this->render('_relation_edit_widget', array(
        'model' => $model,
        'relations' => $relations,
        'relationName' => $relationName,
    ));
}*/
?>
    </div-->

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'), [
            'class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary',
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>