<?php

use netis\utils\crud\ActiveRecord;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $relations array */
/* @var $relationName string */
/* @var $relation array */
/* @var $controller netis\utils\crud\ActiveController */

$relation = $relations[$relationName];
/** @var ActiveRecord $model */
$model = $relation['model'];
?>

<section>
    <h1><?= $model->getCrudLabel('relation') ?></h1>
    <?php Pjax::begin(['id' => $relationName]) ?>
    <?= GridView::widget([
        'dataProvider' => $relation['dataProvider'],
        'columns'      => $relation['columns'],
    ]); ?>
    <?php Pjax::end() ?>
</section>
