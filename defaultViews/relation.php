<?php

use netis\utils\widgets\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array */

$layout = <<<HTML
{quickSearch}
{items}
<div class="row">
    <div class="col-md-6">{pager}</div>
    <div class="col-md-6 summary">{summary}</div>
</div>
HTML;

?>

<?php Pjax::begin([
    'timeout' => 6000,
]); ?>
<?= GridView::widget([
    'id'             => 'relationGrid',
    'dataProvider'   => $dataProvider,
//    'filterModel'    => $searchModel,
    'filterSelector' => '#relationGrid-quickSearch',
    'columns'        => $columns,
    'layout'         => $layout,
]); ?>
<?php Pjax::end(); ?>
