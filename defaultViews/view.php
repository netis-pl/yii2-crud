<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var $this \netis\utils\web\View
 * @var $model yii\db\ActiveRecord
 * @var $attributes array
 * @var $relations array
 * @var $controller netis\utils\crud\ActiveController
 * @var $detailsBody string if set, allows to override only the details part
 * @var $showTitle boolean If set to false <h1> title won't be rendered.
 */

$controller = $this->context;
$this->title = $model->getCrudLabel('read').': '.$model->__toString();
if ($controller instanceof \yii\base\Controller) {
    $this->params['breadcrumbs'] = $controller->getBreadcrumbs($controller->action, $model);
    $this->params['menu']        = $controller->getMenu($controller->action, $model);
}

// skip the whole view if pjax requested specific part
$request = Yii::$app->request;
if ($request instanceof \yii\web\Request && ($relationName = $request->getQueryParam('_pjax')) !== null
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

?>

<?php if (!isset($showTitle) || $showTitle): ?>
    <h1><span><?= Html::encode($this->title) ?></span></h1>
<?php endif;?>

<?= netis\utils\web\Alerts::widget() ?>

<?= isset($detailsBody) ? $detailsBody : DetailView::widget([
    'model' => $model,
    'attributes' => $attributes,
]) ?>

<?= $this->render('_relations', [
    'model' => $model,
    'relations' => $relations,
], $this->context) ?>

