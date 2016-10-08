<?php
/**
 * @var \netis\crud\web\View             $this
 * @var nineinchnick\audit\models\Action $action
 * @var cogpowered\FineDiff\Diff         $diff a diff engine
 */
use netis\crud\helpers\Html;

/** @var \netis\crud\db\ActiveRecord $model */
$model = $action->model;
/** @var \netis\crud\web\Formatter $formatter */
$formatter  = Yii::$app->formatter;
$attributes = array_map(function ($attribute, $value) use ($model, $diff) {
    /** @var \netis\crud\web\Formatter $formatter */
    $formatter = Yii::$app->formatter;
    $format    = $model->getAttributeFormat($attribute);

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
if (empty($action->changed_fields)) {
    return;
}
?>
<hr/>
<h5><?= $action->actionTypeLabel . ' ' . $model->getCrudLabel() . ': ' . $model->__toString() ?></h5>

<div class="change-details table-responsive" id="changeDetails_<?= $action->action_id ?>">
    <table class="table table-striped table-bordered diff-details">
        <thead>
        <tr>
            <th><?= Yii::t('app', 'Attribute');?></th>
            <th><?= Yii::t('app', 'Value before change');?></th>
            <th><?= Yii::t('app', 'Value after change');?></th>
            <th><?= Yii::t('app', 'Difference');?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($action->changed_fields as $attribute => $value): ?>
            <?php
            $relation = null;
            $format = $model->getAttributeFormat($attribute);
            $before = $formatter->format($model->getAttribute($attribute), $format);
            $after = $formatter->format($value, $format);
            $label = $model->getAttributeLabel($attribute);
            foreach ($model->relations() as $relationName) {
                $relation = $model->getRelation($relationName);
                if (reset($relation->link) !== $attribute) {
                    continue;
                }
                /** @var \netis\crud\db\ActiveRecord $class */
                $class = $relation->modelClass;
                $label = $model->getRelationLabel($relation, Html::getAttributeName($relationName));
                $before = (string) ($class::findOne($model->getAttribute($attribute)) ?: $model->getAttribute($attribute));
                $after = (string) ($class::findOne($value) ?: $value);
            }
            ?>
            <tr>
                <th><?= $label; ?></th>
                <td><?= $before; ?></td>
                <td><?= $after; ?></td>
                <td><?= $diff->render(strip_tags(htmlspecialchars_decode($before)), strip_tags(htmlspecialchars_decode($after))); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>