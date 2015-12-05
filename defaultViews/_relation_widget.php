<?php

use netis\crud\widgets\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $relations array */
/* @var $relationName string */
/* @var $relation array */

$relation = $relations[$relationName];
$pjax = null;

/*
FIXME quickSearch is disabled in relations because it doesn't work. Quick search input should have unique id
      (with relation name) and this should be properly parsed when preparing the dataProvider to add filters.
    <div class="col-md-2">{quickSearch}</div>
 */
$layout = <<<HTML
<div class="row">
    <div class="col-md-12">{buttons}</div>
</div>
{items}
<div class="row">
    <div class="col-md-4">{pager}</div>
    <div class="col-md-4 summary">{summary}</div>
    <div class="col-md-4">{lengthPicker}</div>
</div>
HTML;

if (!isset($relation['pjax']) || $relation['pjax']) {
    $pjax = Pjax::begin([
        'id' => $relationName.'Pjax',
        'linkSelector' => false,
    ]);

    $fieldId = \yii\helpers\Html::getInputId($model, $relationName);
    $script = <<<JavaScript
$('#{$relationName}Pjax').data('selectionFields', {'add': '#{$fieldId}-add', 'remove': '#{$fieldId}-remove'});
$(document).pjax('#{$relationName}Pjax a', '#{$relationName}Pjax');
$(document).on('pjax:beforeSend', '#{$relationName}Pjax', function(event, xhr, options) {
  var container = $(event.target);
  xhr.setRequestHeader('X-Selection-add', $(container.data('selectionFields').add).val());
  xhr.setRequestHeader('X-Selection-remove', $(container.data('selectionFields').remove).val());
});
JavaScript;
    $this->registerJs($script);
}

echo GridView::widget([
    'id'           => $relationName.'Grid',
    'dataProvider' => $relation['dataProvider'],
    'filterSelector' => "#{$relationName}Grid-quickSearch",
    'columns'      => $relation['columns'],
    'layout'       => isset($relation['layout']) ? $relation['layout'] : $layout,
    'buttons'      => isset($relation['buttons']) ? $relation['buttons'] : [],
]);
$pjax !== null && Pjax::end();
