<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model netis\utils\crud\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var mixed $sourceState */
/* @var mixed $targetState */
/* @var array $states */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$this->title = $model->getCrudLabel('update').': '.$model->__toString();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);

$format = $model->getAttributeFormat($model->getStateAttributeName());

?>

<?= Yii::t('netis/fsm/app', 'Change status from {source} to {target}', [
    'source' => '<span class="badge badge-default">' . Yii::$app->formatter->format($sourceState, $format) . '</span>',
    'target' => '<span class="badge badge-primary">' . Yii::$app->formatter->format($targetState, $format) . '</span>',
]); ?>

<?= netis\utils\web\Alerts::widget() ?>

<?php if ($targetState === null && is_array($states)): ?>

<?php
foreach ($states as $state) {
    if (!$state['enabled']) {
        continue;
    }
    echo Html::a('<i class="fa fa-'.$state['icon'].'"></i> '.$state['label'], $state['url'], [
        'class' => 'btn '.$state['class'],
        'style' => 'margin-left: 2em;',
    ]);
}
?>

<?php else: ?>

<?= $this->render('_form', [
    'model' => $model,
    'fields' => $fields,
    'relations' => [],
    'formAction' => Url::toRoute([
        $action->id,
        'id' => \netis\utils\crud\Action::exportKey($model->getPrimaryKey(true)),
        'targetState' => $targetState,
        'confirmed' => 1,
    ]),
    'buttons' => [
        Html::submitButton('<i class="fa fa-save"></i>' . Yii::t('netis/fsm/app', 'Confirm'), [
            'class' => 'btn btn-success',
        ]),
        Html::a(Yii::t('netis/fsm/app', 'Cancel'), Url::toRoute([
            (isset($_GET['return'])) ? $_GET['return'] : $controller->action->viewAction,
            'id' => \netis\utils\crud\Action::exportKey($model->getPrimaryKey(true)),
        ])),
    ],
], $this->context) ?>

<?php endif; ?>
