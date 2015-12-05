<?php

use yii\widgets\ListView;

/* @var $this yii\web\View */
/* @var $model array the data model */
/* @var $key array the key value associated with the data item */
/* @var $index integer the zero-based index of the data item in the items array returned by dataProvider */
/* @var $widget ListView the widget instance */
/* @var $diff cogpowered\FineDiff\Diff a diff engine */

/** @var nineinchnick\audit\models\Action[] $actions */
$actions = $model['actions'];
$firstAction = reset($actions);

$formatter = Yii::$app->formatter;
?>

<div class="changeset">
    <h4><?= $firstAction->request_date . ' '
    . Yii::t('app', 'by') . ' ' . $firstAction->user . ' '
    . '<small>' . ($firstAction->request_addr !== null
            ? Yii::t('app', 'from') . ' ' . $firstAction->request_addr
            : '')
    . ' @ ' . $firstAction->request_url . '</small>' ?></h4>

<?php foreach ($actions as $action): ?>
    <div>
        <?= $action->actionTypeLabel . ' ' . $action->model->getCrudLabel() . ': ' . $action->model ?>,
        <?= Yii::t('app', 'changed: ') . implode(', ', array_map(function ($attribute) use ($action) {
            return $action->model->getAttributeLabel($attribute);
        }, array_keys($action->changed_fields))) ?>,

        <a role="button" data-toggle="collapse" href="#changeDetails_<?= $action->action_id ?>"
           aria-expanded="false"
           aria-controls="changeDetails<?= $action->id ?>">
            <?= Yii::t('app', 'Details') ?>
        </a>
    </div>
    <div class="collapse change-details" id="changeDetails_<?= $action->action_id ?>">
        <?= \yii\widgets\DetailView::widget([
            'model' => $action,
            'attributes' => array_map(function ($attribute, $value) use ($action, $diff, $formatter) {
                $format = $action->model->getAttributeFormat($attribute);
                return [
                    'attribute' => $attribute,
                    'label' => $action->model->getAttributeLabel($attribute),
                    'format' => 'raw',
                    'value' => $diff->render(
                        strip_tags($formatter->format($action->model->getAttribute($attribute), $format)),
                        strip_tags($formatter->format($value, $format))
                    ),
                ];
            }, array_keys($action->changed_fields), $action->changed_fields),
            'options' => ['class' => 'table table-striped table-bordered detail-view diff-details'],
        ]) ?>
    </div>
<?php endforeach; ?>
</div>
