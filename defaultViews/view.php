<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\grid\GridView;
use yii\data\ActiveDataProvider;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $attributes array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$this->title = $model->__toString();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);
?>

<h1><?= Html::encode($this->title) ?></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => $attributes,
]) ?>
<!-- just for now -->
<?php foreach ($relations as $relation): ?>
    <section>
        <h1>Nazwa</h1>
<?php
$dataProvider = new ActiveDataProvider(['query' => $relation])
?>

        <?=
        GridView::widget([
            'dataProvider' => $dataProvider,
            //'filterModel' => $searchModel,
//            'columns'      => $columns,
        ]);
        ?>
    </section>
<?php endforeach; ?>