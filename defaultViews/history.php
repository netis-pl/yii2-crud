<?php

use yii\db\ActiveRecord;
use yii\helpers\Html;
use netis\utils\widgets\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model ActiveRecord */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;

if ($model instanceof \netis\utils\crud\ActiveRecord) {
    $this->title                 = $model->getCrudLabel('relation');
    $this->params['breadcrumbs'] = [
        [
            'label' => $model->getCrudLabel('index'),
            'url' => ['index'],
        ],
        [
            'label' => $model->__toString(),
            'url' => ['view', 'id' => $id],
        ],
        Yii::t('app', 'History'),
    ];
    $this->params['menu'] = $controller->getMenu($controller->action, $model);
} else {
    $this->title = Yii::t('app', 'History');
}

$layout = <<<HTML
<div class="row">
    <div class="col-md-3">{quickSearch}</div>
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

<?php Pjax::begin(['id' => 'historyPjax']); ?>
<?= GridView::widget([
    'id'             => 'historyGrid',
    'dataProvider'   => $dataProvider,
//    'filterModel'    => $searchModel,
    'filterSelector' => '#historyGrid-quickSearch',
    'columns'        => [
        'key_type',
        'id',
    ],
    'layout'         => $layout,
]); ?>
<?= \yii\widgets\ListView::widget([
    'id'             => 'historyGrid',
    'dataProvider'   => $dataProvider,
    'itemView'       => '_history_entry',
]); ?>
<?php Pjax::end(); ?>
