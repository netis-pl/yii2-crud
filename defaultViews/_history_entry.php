<?php
use yii\widgets\ListView;

/* @var $this yii\web\View */
/* @var $model array the data model */
/* @var $key array the key value associated with the data item */
/* @var $index integer the zero-based index of the data item in the items array returned by dataProvider */
/* @var $widget ListView the widget instance */

$firstAction = reset($model['actions']);
?>

<div class="changeset">
    <h5><?= $firstAction['request_date'] . ' '
    . Yii::t('app', 'by') . ' ' . $firstAction['user'] . ' '
    . ($firstAction['request_addr'] !== null ? Yii::t('app', 'from') . ' ' . $firstAction['request_addr'] : '') . ' '
    . '@ ' . $firstAction['request_url'] ?></h5>

<?php foreach ($model['actions'] as $action): ?>
    <p>
        <?= $action->actionTypeLabel . ' ' . $action->model->getCrudLabel() . ': ' . $action->model ?>
    </p>
    <p>
        <?= (new Diff(
            array_values(array_intersect_key($action['row_data'], $action['changed_fields'])),
            array_values($action['changed_fields']))
        )->render(new Diff_Renderer_Html_Inline()) ?>
    </p>
<?php endforeach; ?>
</div>
