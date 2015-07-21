<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model netis\utils\crud\ActiveRecord */
/* @var $fields array */
/* @var $relations array */
/* @var array $stateChange contains state and targets keys */
/* @var mixed $sourceState */
/* @var mixed $targetState */
/* @var array $states */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$id = \netis\utils\crud\Action::exportKey($model->getPrimaryKey(true));

$this->title = ($targetState === null ? Yii::t('netis/fsm/app', 'State change') : $stateChange['state']->label);
$this->title .= ': ' . $model->__toString();
$this->params['breadcrumbs'] = [
    [
        'label' => $model->getCrudLabel('index'),
        'url' => ['index'],
    ],
    [
        'label' => $model->__toString(),
        'url' => ['view', 'id' => $id],
    ],
    $targetState === null ? Yii::t('netis/fsm/app', 'State change') : $stateChange['state']->label,
];
$this->params['menu'] = $controller->getMenu($controller->action, $model);

$format = $model->getAttributeFormat($model->getStateAttributeName());

?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

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
    'relations' => $relations,
    'formOptions' => [
        'action' => Url::toRoute([
            $controller->action->id,
            'id' => $id,
            'targetState' => $targetState,
            'confirmed' => 1,
        ]),
        'enableAjaxValidation' => false,
    ],
    'buttons' => [
        Html::submitButton('<i class="fa fa-save"></i>' . Yii::t('netis/fsm/app', 'Confirm'), [
            'class' => 'btn btn-success',
        ]),
        Html::a(Yii::t('netis/fsm/app', 'Cancel'), Url::toRoute([
            Yii::$app->request->getQueryParam('return', $controller->action->viewAction),
            'id' => \netis\utils\crud\Action::exportKey($model->getPrimaryKey(true)),
        ])),
    ],
], $this->context) ?>

<?php endif; ?>
