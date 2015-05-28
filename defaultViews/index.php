<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel netis\utils\db\ActiveSearchTrait */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$searchModel = $controller->getSearchModel();
$this->title = $searchModel->getCrudLabel();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $searchModel);
$this->params['menu'] = $controller->getMenu($controller->action, $searchModel);
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>
<?php
$script = <<<JS
jQuery('#w0').yiiGridView({'filterUrl':'\/assortment\/product','filterSelector':'#form-control'});
JS;
//$this->registerJs($script,3);
?>
<?php
$script = <<<JS
jQuery('body').on('keyup','.form-control',function(){
    $("#w0").yiiGridView("applyFilter");})
JS;
//$this->registerJs($script,3);
?>
<?= netis\utils\web\Alerts::widget() ?>
<div class="input-group" style="width: 200px;">
    <span class="input-group-addon"><i class="fa fa-search"></i></span>
    <!--<input onkeyup="jQuery('#w0').addClass('asd');"-->
    <div id="w0-filters">
    <input onkeyup="jQuery('#w0').yiiGridView('applyFilter')"
        class="form-control" id="form-control" name="Product[Symbol]" placeholder="<?php echo Yii::t('app', 'Search'); ?>" type="text"/>
    </div>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'columns' => $columns,
]); ?>
