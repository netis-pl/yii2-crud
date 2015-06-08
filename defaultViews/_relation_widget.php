<?php

use netis\utils\crud\ActiveRecord;
use netis\utils\widgets\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $relations array */
/* @var $relationName string */
/* @var $relation array */
/* @var $isActive boolean */
/* @var $buttons array */
/* @var $controller netis\utils\crud\ActiveController */

$relation = $relations[$relationName];
/** @var ActiveRecord $model */
$model = $relation['model'];

$layout = <<<HTML
<div class="row">
    <div class="col-md-2">{quickSearch}</div>
    <div class="col-md-10">{buttons}</div>
</div>
{items}
<div class="row">
    <div class="col-md-4">{pager}</div>
    <div class="col-md-4 summary">{summary}</div>
    <div class="col-md-4">{lengthPicker}</div>
</div>
HTML;
?>

<div role="tabpanel"
     class="tab-pane fade<?= $isActive ? ' in active' : '' ?>"
     id="tab_<?= $relationName ?>">
    <?php Pjax::begin(['id' => $relationName.'Pjax']); ?>
    <?= GridView::widget([
        'id'           => $relationName.'Grid',
        'dataProvider' => $relation['dataProvider'],
        'filterSelector' => '#quickSearchIndex',
        'columns'      => $relation['columns'],
        'layout'       => $layout,
        'buttons'      => isset($buttons) ? $buttons : [],
    ]); ?>
    <?php Pjax::end(); ?>
</div>
