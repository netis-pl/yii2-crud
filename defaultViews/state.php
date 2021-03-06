<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var $this netis\crud\web\View
 * @var $model netis\crud\db\ActiveRecord
 * @var $fields array
 * @var $relations array
 * @var $stateChange array contains state and targets keys
 * @var $sourceState mixed
 * @var $targetState mixed
 * @var $states array
 * @var $controller netis\crud\crud\ActiveController
 * @var $showTitle boolean If set to false <h1> title won't be rendered.
 * @var $formOptions array form options, will be merged with defaults
 */

$controller = $this->context;
$id = \netis\crud\crud\Action::exportKey($model->getPrimaryKey(true));

$this->title = ($targetState === null ? Yii::t('netis/fsm/app', 'State change') : $stateChange['state']->label);
$this->title .= ': ' . $model->__toString();
if ($controller instanceof \yii\base\Controller) {
    $this->params['breadcrumbs'] = [
        [
            'label' => $model->getCrudLabel('index'),
            'url'   => ['index'],
        ],
        [
            'label' => $model->__toString(),
            'url'   => ['view', 'id' => $id],
        ],
        $targetState === null ? Yii::t('netis/fsm/app', 'State change') : $stateChange['state']->label,
    ];
    $this->params['menu'] = $controller->getMenu($controller->action, $model);
}

$format = $model->getAttributeFormat($model->getStateAttributeName());

?>

<?php if (!isset($showTitle) || $showTitle): ?>
    <h1><span><?= Html::encode($this->title) ?></span></h1>
<?php endif;?>
<?= netis\crud\web\Alerts::widget() ?>

<?php if ($targetState === null && is_array($states)) : ?>

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

<?php else : ?>

<?php
if (!isset($buttons)) {
    $icon = Html::tag('i', '', ['class' => 'fa fa-'. $stateChange['state']->icon]);
    $buttons = [
        Html::submitButton($icon . $stateChange['state']->label, [
            'class' => 'btn ' . $stateChange['state']->css_class,
        ]),
        Html::a(Yii::t('app', 'Back'), Url::toRoute([
            Yii::$app->request->getQueryParam('return', $controller->action->viewAction),
            'id' => \netis\crud\crud\Action::exportKey($model->getPrimaryKey(true)),
        ]), [
            'class' => 'btn btn-default',
        ]),
    ];
}
echo Html::hiddenInput('target-state', $targetState, ['id' => 'target-state']);
echo $this->render('_form', [
    'model' => $model,
    'targetState' => $targetState,
    'fields' => $fields,
    'relations' => $relations,
    'formOptions' => array_merge([
        'action' => Url::toRoute([
            $controller->action->id,
            'id' => $id,
            'targetState' => $targetState,
            'confirmed' => 1,
        ]),
        'enableAjaxValidation' => false,
    ], isset($formOptions) ? $formOptions : []),
    'buttons' => $buttons,
], $this->context) ?>

<?php endif; ?>
