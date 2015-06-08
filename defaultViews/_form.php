<?php

use yii\base\Model;
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\helpers\Json;
use yii\widgets\PjaxAsset;

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
            echo call_user_func_array([$field, $data['formMethod']], $data['arguments']);
        } else {
            echo call_user_func($data['formMethod'], $field, $data['arguments']);
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
    echo $field
        ->label($label, ['class' => 'control-label'])
        ->error(['class' => 'help-block'])
        ->widget($data['widgetClass'], $data['options']);
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
    if (($route = Yii::$app->crudModelsMap[$data['model']::className()]) !== null) {
        $route = \yii\helpers\Url::toRoute([
            $route . '/relation',
            'per-page' => 10,
            'relation' => $data['dataProvider']->query->inverseOf,
            'id' => \netis\utils\crud\Action::exportKey($model->getPrimaryKey()),
        ]);
    }
    echo Html::activeHiddenInput($model, $relationName.'[add]', ['value' => '[]']);
    echo Html::activeHiddenInput($model, $relationName.'[remove]', ['value' => '[]']);
    echo $this->render('_relation_widget', [
        'model' => $model,
        'relations' => $relations,
        'relationName' => $relationName,
        'isActive' => $relationName === $activeRelation,
        'buttons' => [
            \yii\helpers\Html::a('<span class="glyphicon glyphicon-plus"></span>', '#', [
                'title'         => Yii::t('app', 'Add'),
                'aria-label'    => Yii::t('app', 'Add'),
                'data-pjax'     => '0',
                'data-toggle'   => 'modal',
                'data-target'   => '#relationModal',
                'data-relation' => $relationName,
                'data-title'    => $data['model']->getCrudLabel('index'),
                'data-pjax-url' => $route,
                'class'         => 'btn btn-default',
            ]),
        ],
    ]);
}
?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('app', 'Create') : Yii::t('app', 'Update'), [
            'class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary',
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>