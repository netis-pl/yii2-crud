<?php

use netis\crud\crud\IndexAction;
use netis\crud\widgets\FormBuilder;
use yii\helpers\Html;
use netis\crud\widgets\GridView;
use yii\helpers\Url;
use yii\widgets\Pjax;

/**
 * @var $this netis\crud\web\View
 * @var $dataProvider yii\data\ActiveDataProvider
 * @var $columns array
 * @var $buttons array each entry is an array with keys: icon, label, url, options
 * @var $searchModel \netis\crud\db\ActiveRecord
 * @var $searchFields array
 * @var $controller netis\crud\crud\ActiveController
 * @var $showTitle boolean If set to false <h1> title won't be rendered.
 * @var $searchModes integer combination of IndexAction::SEARCH_* constants - what kind of search options are available
 */

$controller = $this->context;
/** @var IndexAction $action */
$action = $controller->action;
if (!isset($gridOptions) || !is_array($gridOptions)) {
    $gridOptions = [];
}
$gridId = 'indexGrid';

if ($searchModel instanceof \netis\crud\db\ActiveRecord) {
    if ($this->title === null) {
        $this->title = $searchModel->getCrudLabel('relation');
    }
    if ($controller instanceof \yii\base\Controller) {
        $this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
        $this->params['menu']        = $controller->getMenu($controller->action, $searchModel);
    }
} elseif ($this->title === null) {
    $this->title = Yii::t('app', 'Browse');
}

if (!isset($searchModes)) {
    $searchModes = IndexAction::SEARCH_ADVANCED_FORM | IndexAction::SEARCH_QUICK_SEARCH;
}
if ($searchModes & IndexAction::SEARCH_COLUMN_HEADERS) {
    $gridOptions['filterModel'] = $searchModel;
}
if ($searchModes & IndexAction::SEARCH_ADVANCED_FORM) {
    array_unshift($buttons, [
        'label' => Yii::t('app', 'Advanced search'),
        'url' => '#advancedSearch',
        'options' => [
            'class' => 'btn btn-default',
            'data-toggle' => 'collapse',
            'aria-expanded' => false,
            'aria-controls' => 'advancedSearch',
        ]
    ]);
}
$buttonsTemplate = implode("\n        ", array_map(function ($button) {
    $icon = isset($button['icon']) ? '<i class="'.$button['icon'].'"></i> ' : '';
    return Html::a($icon . $button['label'], $button['url'], $button['options']);
}, $buttons));

if ($searchModes & IndexAction::SEARCH_QUICK_SEARCH) {
    $gridOptions['filterSelector'] = "#{$gridId}-quickSearch";
    $buttonsTemplate = <<<HTML
<div class="row">
    <div class="col-md-3">{quickSearch}</div>
    <div class="col-md-9">
        $buttonsTemplate
    </div>
</div>
HTML;
} else if (trim($buttonsTemplate) !== '') {
    $buttonsTemplate = $buttonsTemplate . '<br/><br/>';
}

$layout = <<<HTML
$buttonsTemplate
{items}
<div class="row">
    <div class="col-md-4">{pager}</div>
    <div class="col-md-4 summary">{summary}</div>
    <div class="col-md-4">{lengthPicker}</div>
</div>
HTML;

if (!isset($showTitle) || $showTitle) {
    echo '<h1><span>' . Html::encode($this->title) . '</span></h1>';
}

echo netis\crud\web\Alerts::widget();
if ($searchModes & IndexAction::SEARCH_ADVANCED_FORM) {
    echo $this->render('_search', [
        'model'  => $searchModel,
        'fields' => $searchFields,
    ]);
} elseif ($searchModes & IndexAction::SEARCH_COLUMN_HEADERS) {
    FormBuilder::registerSelect($this);
    echo FormBuilder::registerRelations($this);
}

Pjax::begin(['id' => 'indexPjax']);
echo GridView::widget(array_merge([
    'id'             => $gridId,
    'dataProvider'   => $dataProvider,
    // this actually renders some widgets and must be called after Pjax::begin()
    'columns'        => $action->addColumnFilters($columns, $searchFields),
    'layout'         => $layout,
], $gridOptions));
Pjax::end();

if ($searchModes & IndexAction::SEARCH_COLUMN_HEADERS) {
    //@todo implement filtering on keyup after timeout.
    //@todo move this to separate js file and make library from it.
    $script = <<<JavaScript
var enterPressed = false;
$(document)
    .off('change.yiiGridView keydown.yiiGridView', '#{$gridId}-filters input, #{$gridId}-filters select')
    .on('change.yiiGridView keydown.yiiGridView', '#{$gridId}-filters input.form-control', function (event) {
        if (event.type === 'keydown') {
            if (event.keyCode !== 13) {
                return; // only react to enter key
            } else {
                enterPressed = true;
            }
        } else {
            // prevent processing for both keydown and change events
            if (enterPressed) {
                enterPressed = false;
                return;
            }
        }

        $('#{$gridId}').yiiGridView('applyFilter');

        return false;
    })
    .on('change', '#{$gridId}-filters input.select2, #{$gridId}-filters select.select2', function (event) {
        $('#{$gridId}').yiiGridView('applyFilter');
        return false;
    })
    .on( "autocompleteselect", '#{$gridId}-filters input.ui-autocomplete-input', function( event, ui ) {
        $(this).val(ui.item.value);
        $('#{$gridId}').yiiGridView('applyFilter');
        return false;
    });
JavaScript;
    $this->registerJs($script);
}
