<?php

use netis\utils\crud\Action;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model netis\utils\crud\ActiveRecord */
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
                <?= $model->getRelationLabel($data['dataProvider']->query, $relationName) ?>
            </a>
        </li>
<?php endforeach; ?>
    </ul>
    <div class="tab-content">
        <?php foreach ($relations as $relationName => $data): ?>
            <div role="tabpanel"
                 class="tab-pane fade<?= $relationName === $activeRelation ? ' in active' : '' ?>"
                 id="tab_<?= $relationName ?>">
                <?php
                /** @var \yii\db\ActiveRecord $relationModel */
                $relationModel = $data['model'];
                /** @var \yii\db\ActiveQuery $query */
                $query = clone $data['dataProvider']->query;
                $keys = Action::implodeEscaped(
                    Action::KEYS_SEPARATOR,
                    array_map(
                        '\netis\utils\crud\Action::exportKey',
                        $query->select($relationModel->getTableSchema()->primaryKey)
                            ->asArray()
                            ->all()
                    )
                );?>
                <?= Html::activeHiddenInput($model, $relationName.'[add]', ['value' => $keys]) ?>
                <?= Html::activeHiddenInput($model, $relationName.'[remove]') ?>
                <?= $this->render('_relation_widget', [
                    'model' => $model,
                    'relations' => $relations,
                    'relationName' => $relationName,
                ], $this->context) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
