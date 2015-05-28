<?php

use netis\utils\crud\ActiveRecord;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $relations array */
/* @var $relationName string */
/* @var $relation array */
/* @var $isActive boolean */
/* @var $controller netis\utils\crud\ActiveController */

$relation = $relations[$relationName];
/** @var ActiveRecord $model */
$model = $relation['model'];
?>

<!--div class="panel panel-default">
    <div class="panel-heading" role="tab" id="heading<?= $relationName ?>">
        <h4 class="panel-title">
            <a data-toggle="collapse" data-parent="#relationsAccordion" href="#collapse<?= $relationName ?>"
               aria-expanded="true" aria-controls="collapse<?= $relationName ?>">
                <?= $model->getCrudLabel('relation'); ?>
            </a>
        </h4>
    </div>
    <div id="collapse<?= $relationName ?>" class="panel-collapse collapse" role="tabpanel"
         aria-labelledby="heading<?= $relationName ?>">
        <div class="panel-body">
            <?php /*Pjax::begin(['id' => $relationName]); ?>
            <?= GridView::widget([
                'dataProvider' => $relation['dataProvider'],
                'columns'      => $relation['columns'],
            ]); ?>
            <?php Pjax::end();*/ ?>
        </div>
    </div>
</div-->

<div role="tabpanel"
     class="tab-pane fade<?= $isActive ? ' in active' : '' ?>"
     id="tab_<?= $relationName ?>">
    <?php Pjax::begin(['id' => $relationName]); ?>
    <?= GridView::widget([
        'dataProvider' => $relation['dataProvider'],
        'columns'      => $relation['columns'],
    ]); ?>
    <?php Pjax::end(); ?>
</div>

