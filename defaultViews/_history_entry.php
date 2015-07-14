<?php
use yii\widgets\ListView;

/* @var $this yii\web\View */
/* @var $model array the data model */
/* @var $key array the key value associated with the data item */
/* @var $index integer the zero-based index of the data item in the items array returned by dataProvider */
/* @var $widget ListView the widget instance */

$firstAction = reset($model['actions']);
?>

<h6><?= $firstAction['request_date'] . ' '
. Yii::t('app', 'by') . ' ' . $firstAction['user'] . ' '
. Yii::t('app', 'from') . ' ' . $firstAction['request_addr'] . ' '
. '@ ' . $firstAction['request_url'] ?></h6>

<?php foreach ($model['actions'] as $action): ?>
    <p><?= $action['action_type'] . ' ' . $action['model']->getCrudLabel() ?></p>
    <p><?= (new Diff(array_values(array_intersect_key($action['row_data'], $action['changed_fields'])), array_values($action['changed_fields'])))
        ->render(new Diff_Renderer_Html_Inline) ?></p>
<?php endforeach; ?>
