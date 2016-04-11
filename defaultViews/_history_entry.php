<?php

use netis\crud\helpers\Html;
use yii\widgets\ListView;

/**
 * @var $this yii\web\View
 * @var $model array the data model
 * @var $key array the key value associated with the data item
 * @var $index integer the zero-based index of the data item in the items array returned by dataProvider
 * @var $widget ListView the widget instance
 * @var $diff cogpowered\FineDiff\Diff a diff engine
 */

/** @var nineinchnick\audit\models\Action[] $actions */
$actions = $model['actions'];
$firstAction = reset($actions);

$formatter = Yii::$app->formatter;

$body = '';
foreach ($actions as $action) {
    $body .= $this->render('_history_entry_action', ['action' => $action, 'diff' => $diff]);
}
echo Html::historyEntry($firstAction->user , $formatter->asDatetime($firstAction->request_date), $body, []);
