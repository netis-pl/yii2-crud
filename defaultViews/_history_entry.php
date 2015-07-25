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
    <h5><?= $firstAction->request_date . ' '
    . Yii::t('app', 'by') . ' ' . $firstAction->user . ' '
    . ($firstAction->request_addr !== null ? Yii::t('app', 'from') . ' ' . $firstAction->request_addr : '') . ' '
    . '@ ' . $firstAction->request_url ?></h5>

<?php foreach ($actions as $action): ?>
    <p>
        <?= $action->actionTypeLabel . ' ' . $action->model->getCrudLabel() . ': ' . $action->model ?>
    </p>
    <p>
        <?= \yii\widgets\DetailView::widget([
            'model' => $action,
            'attributes' => array_map(function ($attribute, $value) use ($action, $diff, $formatter) {
                $format = $action->model->getAttributeFormat($attribute);
                return [
                    'attribute' => $attribute,
                    'label' => $action->model->getAttributeLabel($attribute),
                    'value' => $diff->render(
                        $formatter->format($action->model->getAttribute($attribute), $format),
                        $formatter->format($value, $format)
                    ),
                ];
            }, array_keys($action->changed_fields), $action->changed_fields),
        ]) ?>
    </p>
<?php endforeach; ?>
</div>
