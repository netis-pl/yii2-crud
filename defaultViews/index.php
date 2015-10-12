<?php

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
 */

$controller = $this->context;
if (!isset($gridOptions) || !is_array($gridOptions)) {
    $gridOptions = [];
}

if ($searchModel instanceof \netis\utils\crud\ActiveRecord) {
    $this->title                 = $searchModel->getCrudLabel('relation');
    $this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
    $this->params['menu']        = $controller->getMenu($controller->action, $searchModel);
} else {
    $this->title = Yii::t('app', 'Browse');
}

$searchLabel = Yii::t('app', 'Advanced search');
$buttonsTemplate = implode("\n        ", array_map(function ($button) {
    $icon = isset($button['icon']) ? '<i class="'.$button['icon'].'"></i> ' : '';
    return Html::a($icon . $button['label'], $button['url'], $button['options']);
}, $buttons));

$layout = <<<HTML
<div class="row">
    <div class="col-md-3">{quickSearch}</div>
    <div class="col-md-9">
        <a class="btn btn-default" data-toggle="collapse" href="#advancedSearch"
           aria-expanded="false" aria-controls="advancedSearch">$searchLabel</a>
        $buttonsTemplate
    </div>
</div>
{items}
<div class="row">
    <div class="col-md-4">{pager}</div>
    <div class="col-md-4 summary">{summary}</div>
    <div class="col-md-4">{lengthPicker}</div>
</div>
HTML;

?>

<?php if (!isset($showTitle) || $showTitle): ?>
    <h1><span><?= Html::encode($this->title) ?></span></h1>
<?php endif;?>

<?= netis\utils\web\Alerts::widget() ?>
<?= $this->render('_search', [
    'model' => $searchModel,
    'fields' => $searchFields,
]); ?>

<?php Pjax::begin(['id' => 'indexPjax']); ?>
<?= GridView::widget(array_merge([
    'id'             => 'indexGrid',
    'dataProvider'   => $dataProvider,
//    'filterModel'    => $searchModel,
    'filterSelector' => '#indexGrid-quickSearch',
    'columns'        => $columns,
    'layout'         => $layout,
], $gridOptions)); ?>
<?php Pjax::end(); ?>
