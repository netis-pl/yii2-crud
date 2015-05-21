<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $model yii\db\ActiveRecord */

$this->title = $searchModel->getCrudLabel('create');
$this->params['breadcrumbs'][] = ['label' => $searchModel->getCrudLabel('index'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ar-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'searchModel' => $searchModel,
        'model' => $model,
    ]) ?>

</div>