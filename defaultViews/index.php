<?php

use netis\utils\crud\IndexAction;
use netis\utils\widgets\FormBuilder;
use yii\helpers\Html;
use netis\utils\widgets\GridView;
use yii\widgets\Pjax;

/**
 * @var $this netis\utils\web\View
 * @var $dataProvider yii\data\ActiveDataProvider
 * @var $columns array
 * @var $buttons array each entry is an array with keys: icon, label, url, options
 * @var $searchModel \yii\base\Model
 * @var $searchFields array
 * @var $controller netis\utils\crud\ActiveController
 * @var $showTitle boolean If set to false <h1> title won't be rendered.
 * @var $searchModes integer combination of IndexAction::SEARCH_* constants - what kind of search options are available
 */

$controller = $this->context;
if (!isset($gridOptions) || !is_array($gridOptions)) {
    $gridOptions = [];
}

if ($searchModel instanceof \netis\utils\crud\ActiveRecord) {
    if ($this->title === null) {
        $this->title = $searchModel->getCrudLabel('relation');
    }
    $this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
    $this->params['menu']        = $controller->getMenu($controller->action, $searchModel);
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
    $gridOptions['filterSelector'] = '#indexGrid-quickSearch';
    $buttonsTemplate = <<<HTML
<div class="row">
    <div class="col-md-3">{quickSearch}</div>
    <div class="col-md-9">
        $buttonsTemplate
    </div>
</div>
HTML;
} else {
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

echo netis\utils\web\Alerts::widget();
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
    'id'             => 'indexGrid',
    'dataProvider'   => $dataProvider,
    // this actually renders some widgets and must be called after Pjax::begin()
    'columns'        => $controller->action->addColumnFilters($columns, $searchFields),
    'layout'         => $layout,
], $gridOptions));
Pjax::end();
