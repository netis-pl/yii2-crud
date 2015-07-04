<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $relations array */
/* @var $form yii\widgets\ActiveForm */
/* @var $controller netis\utils\crud\ActiveController */
/* @var $view \netis\utils\web\View */

$controller = $this->context;
$view = $this;

$pjax = Yii::$app->request->getQueryParam('_pjax');
$activeRelation = false;
foreach ($relations as $relationName => $data) {
    if ($pjax === null || $pjax === "#{$relationName}Pjax") {
        $activeRelation = $relationName;
        break;
    }
}
?>

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
        <?php foreach ($relations as $relationName => $data): ?>
            <?= Html::activeHiddenInput($model, $relationName.'[add]') ?>
            <?= Html::activeHiddenInput($model, $relationName.'[remove]') ?>
            <?= $this->render('_relation_widget', [
                'model' => $model,
                'relations' => $relations,
                'relationName' => $relationName,
                'isActive' => $relationName === $activeRelation,
            ], $this->context) ?>
        <?php endforeach; ?>
    </div>
</div>
