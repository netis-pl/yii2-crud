<?php
/**
 * @var \netis\crud\web\View $this
 * @var nineinchnick\audit\models\Action $action
 * @var cogpowered\FineDiff\Diff $diff a diff engine
 */

/** @var \netis\crud\db\ActiveRecord $model */
$model = $action->model;
$attributes = array_map(function ($attribute, $value) use ($model, $diff) {
    /** @var \netis\crud\web\Formatter $formatter */
    $formatter = Yii::$app->formatter;
    $format = $model->getAttributeFormat($attribute);
    return [
        'attribute' => $attribute,
        'label'     => $model->getAttributeLabel($attribute),
        'format'    => 'raw',
        'value'     => $diff->render(
            strip_tags($formatter->format($model->getAttribute($attribute), $format)),
            strip_tags($formatter->format($value, $format))
        ),
    ];
}, array_keys($action->changed_fields), $action->changed_fields);

?>
<div>
    <?= $action->actionTypeLabel . ' ' . $model->getCrudLabel() . ': ' . $model->__toString() ?>,
    <a role="button" data-toggle="collapse" href="#changeDetails_<?= $action->action_id ?>"
       aria-expanded="false"
       aria-controls="changeDetails<?= $action->id ?>">
        <?= Yii::t('app', 'Details') ?>
    </a>
</div>
<div class="collapse change-details" id="changeDetails_<?= $action->action_id ?>">
    <?= \yii\widgets\DetailView::widget([
        'model'      => $action,
        'attributes' => $attributes,
        'options'    => ['class' => 'table table-striped table-bordered detail-view diff-details'],
    ]) ?>
</div>