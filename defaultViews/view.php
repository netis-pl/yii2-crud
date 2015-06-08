<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model yii\db\ActiveRecord */
/* @var $attributes array */
/* @var $relations array */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;
$this->title = $model->getCrudLabel('read').': '.$model->__toString();
$this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
$this->params['menu'] = $controller->getMenu($controller->action, $model);

// skip the whole view if pjax requested specific part
if (($relationName = Yii::$app->request->getQueryParam('_pjax')) !== null
    && ($relationName = substr($relationName, 1)) !== ''
    && isset($relations[$relationName])
) {
    echo $this->render('_relation_widget', [
        'model' => $relations[$relationName]['model'],
        'relations' => $relations,
        'relationName' => $relationName,
    ]);
    return;
}

$pjax = Yii::$app->request->getQueryParam('_pjax');
$activeRelation = false;
foreach ($relations as $relationName => $data) {
    if ($pjax === null || $pjax === "#$relationName") {
        $activeRelation = $relationName;
        break;
    }
}
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>

<?= netis\utils\web\Alerts::widget() ?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => $attributes,
])
?>
<div role="tabpanel" class="relations-panel">
    <ul class="nav nav-tabs" role="tablist">
<?php foreach ($relations as $relationName => $data): ?>
        <li role="presentation"
            class="<?= $relationName === $activeRelation ? 'active' : ''?>">
            <a href="#tab_<?= $relationName ?>" aria-controls="tab_<?= $relationName ?>"
               role="tab" data-toggle="tab">
                <?= $data['model']->getCrudLabel('relation') ?>
            </a>
        </li>
<?php endforeach; ?>
    </ul>
    <div class="tab-content">
        <?php
        foreach ($relations as $relationName => $data) {
            echo $this->render('_relation_widget', array(
                'model' => $model,
                'relations' => $relations,
                'relationName' => $relationName,
                'isActive' => $relationName === $activeRelation,
            ));
        }
        ?>
    </div>
</div>
