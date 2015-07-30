<?php

use yii\helpers\Html;
use netis\utils\widgets\GridView;
use yii\widgets\Pjax;


/* @var $this yii\web\View */
/* @var $searchModel \yii\base\Model */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array */
/* @var $searchFields array*/
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;

if ($searchModel instanceof \netis\utils\crud\ActiveRecord) {
    $this->title                 = $searchModel->getCrudLabel('relation');
    $this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
    $this->params['menu']        = $controller->getMenu($controller->action, $searchModel);
} else {
    $this->title = Yii::t('app', 'Browse');
}

$searchLabel = Yii::t('app', 'Advanced search');
/** @var \yii\filters\ContentNegotiator $negotiator */
$negotiator = $controller->getBehavior('contentNegotiator');
$excelUrl = \yii\helpers\Url::current([$negotiator->formatParam => \netis\utils\web\Response::FORMAT_XLS]);
$csvUrl = \yii\helpers\Url::current([$negotiator->formatParam => \netis\utils\web\Response::FORMAT_CSV]);
$layout = <<<HTML
<div class="row">
    <div class="col-md-3">{quickSearch}</div>
    <div class="col-md-3">
        <a class="btn btn-default" data-toggle="collapse" href="#advancedSearch"
           aria-expanded="false" aria-controls="advancedSearch">$searchLabel</a>
        <a class="btn btn-default" data-pjax="0" href="$excelUrl"><i class="glyphicon glyphicon-file"></i> XLS</a>
        <a class="btn btn-default" data-pjax="0" href="$csvUrl"><i class="glyphicon glyphicon-file"></i> CSV</a>
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

<h1><span><?= Html::encode($this->title) ?></span></h1>
<?= netis\utils\web\Alerts::widget() ?>
<?= $this->render('_search', [
    'model' => $searchModel,
    'fields' => $searchFields,
]); ?>

<?php Pjax::begin(['id' => 'indexPjax']); ?>
<?= GridView::widget([
    'id'             => 'indexGrid',
    'dataProvider'   => $dataProvider,
//    'filterModel'    => $searchModel,
    'filterSelector' => '#indexGrid-quickSearch',
    'columns'        => $columns,
    'layout'         => $layout,
]); ?>
<?php Pjax::end(); ?>
