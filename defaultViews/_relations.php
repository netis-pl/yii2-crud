<?php

use netis\crud\crud\Action;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model netis\crud\db\ActiveRecord */
/* @var $relations array */
/* @var $controller netis\crud\crud\ActiveController */
/* @var $renderKeyInputs bool */

$controller = $this->context;

$request = Yii::$app->request;
$pjax = $request instanceof \yii\web\Request ? $request->getQueryParam('_pjax') : null;
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
<?php
foreach ($relations as $relationName => $data) {
    echo Html::beginTag('div', [
            'role' => 'tabpanel',
            'id' => 'tab_' . $relationName,
            'class' => 'tab-pane fade' . ($relationName === $activeRelation ? ' in active' : '')]
    );
    if (!isset($renderKeyInputs) || $renderKeyInputs) {
        /** @var \yii\db\ActiveRecord $relationModel */
        $relationModel = $data['model'];
        /** @var \yii\db\ActiveQuery $query */
        $query = clone $data['dataProvider']->query;
        $keys  = Action::implodeEscaped(
            Action::KEYS_SEPARATOR,
            array_map(
                '\netis\crud\crud\Action::exportKey',
                $query->select($relationModel->getTableSchema()->primaryKey)
                    ->asArray()
                    ->all()
            )
        );
        echo Html::activeHiddenInput($model, $relationName . '[add]', ['value' => $keys]);
        echo Html::activeHiddenInput($model, $relationName . '[remove]');
    }
    echo $this->render('_relation_widget', [
        'model'        => $model,
        'relations'    => $relations,
        'relationName' => $relationName,
        'isActive'     => $relationName === $activeRelation,
    ], $this->context);
    echo Html::endTag('div');
}
?>
    </div>
</div>
