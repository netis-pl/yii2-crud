<?php

use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $relations array */
/* @var $relationName string */
/* @var $relation array */
/* @var $controller netis\utils\crud\ActiveController */

$relation = $relations[$relationName];
?>

<section>
    <h1><?= 'Name' ?></h1>
    <?= GridView::widget([
        'dataProvider' => $relation['dataProvider'],
        'columns'      => $relation['columns'],
    ]); ?>
</section>
