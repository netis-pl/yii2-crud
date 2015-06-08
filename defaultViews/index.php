<?php

use yii\helpers\Html;
use netis\utils\widgets\GridView;
use yii\widgets\Pjax;


/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$searchModel = $controller->getSearchModel();
$this->title = $searchModel->getCrudLabel('relation');
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
$this->params['menu'] = $controller->getMenu($controller->action, $searchModel);

$layout = <<<HTML
{quickSearch}
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

<?php Pjax::begin([
    'id' => 'indexPjax',
    'timeout' => 6000,
]); ?>
<?= GridView::widget([
    'id'             => 'indexGrid',
    'dataProvider'   => $dataProvider,
//    'filterModel'    => $searchModel,
    'filterSelector' => '#quickSearchIndex',
    'columns'        => $columns,
    'layout'         => $layout,
]); ?>
<?php Pjax::end(); ?>
