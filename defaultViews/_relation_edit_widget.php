<?php

use netis\utils\crud\ActiveRecord;
use yii\grid\GridView;
use yii\widgets\Pjax;

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

<!--section>
    <h1><?= 'Name' ?></h1>
    <?= ''/*GridView::widget([
        'dataProvider' => $relation['dataProvider'],
        'columns'      => $relation['columns'],
    ]);*/ ?>
</section-->
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
     class="tab-pane fade<?= Yii::$app->request->getQueryParam('_pjax') === "#$relationName" ? ' active' : '' ?>"
     id="tab_<?= $relationName ?>">
    <?php Pjax::begin(['id' => $relationName, 'timeout' => 6000]); ?>
    <?= GridView::widget([
        'dataProvider' => $relation['dataProvider'],
        'columns'      => $relation['columns'],
    ]); ?>
    <?php Pjax::end(); ?>
</div>

