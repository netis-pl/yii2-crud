<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;


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
<?= netis\utils\web\Alerts::widget() ?>

<?php Pjax::begin([
    'timeout' => 6000,
]); ?>
<div class="input-group" style="width: 200px;">
    <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
    <form data-pjax>
        <div id="indexGrid-filters">
            <input onkeyup="jQuery('#indexGrid').yiiGridView('applyFilter');"
                   class="form-control" id="quickSearchIndex" name="search"
                   placeholder="<?php echo Yii::t('app', 'Search'); ?>" type="text" />
        </div>
    </form>
</div>

<?= GridView::widget([
    'id' => 'indexGrid',
    'dataProvider' => $dataProvider,
//    'filterModel' => $searchModel,
    'filterSelector' => '#quickSearchIndex',
    'columns' => $columns,
]); ?>
<?php Pjax::end(); ?>