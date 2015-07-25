<?php

use yii\db\ActiveRecord;
use yii\helpers\Html;
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

$diff = new cogpowered\FineDiff\Diff;
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>
<?= netis\utils\web\Alerts::widget() ?>

<?php Pjax::begin(['id' => 'historyPjax']); ?>
<?= \yii\widgets\ListView::widget([
    'id'             => 'historyGrid',
    'dataProvider'   => $dataProvider,
    'itemView'       => '_history_entry',
    'viewParams'     => ['diff' => $diff],
]); ?>
<?php Pjax::end(); ?>
