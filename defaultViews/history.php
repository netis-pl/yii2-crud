<?php

use yii\bootstrap\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\widgets\Pjax;

/**
 * @var $this yii\web\View
 * @var $model ActiveRecord
 * @var $dataProvider yii\data\ActiveDataProvider
 * @var $controller netis\utils\crud\ActiveController
 * @var $searchModel nineinchnick\audit\models\ActionSearch
 * @var $showTitle boolean If set to false <h1> title won't be rendered.
 */

$controller = $this->context;

if ($model instanceof \netis\utils\crud\ActiveRecord) {
    $this->title                 = $model->getCrudLabel('relation');
    if ($controller instanceof \yii\base\Controller) {
        $this->params['breadcrumbs'] = [
            [
                'label' => $model->getCrudLabel('index'),
                'url'   => ['index'],
            ],
            [
                'label' => $model->__toString(),
                'url'   => ['view', 'id' => $model->id],
            ],
            Yii::t('app', 'History'),
        ];
        $this->params['menu'] = $controller->getMenu($controller->action, $model);
    }
} else {
    $this->title = Yii::t('app', 'History');
}

$css = <<<CSS
.diff-details del {
    background-color: #e99;
    color: #411;
}
.diff-details ins {
    background-color: #9e9;
    color: #131;
}
CSS;

$this->registerCss($css);

$diff = new cogpowered\FineDiff\Diff(new cogpowered\FineDiff\Granularity\Word);
?>

<?php if (!isset($showTitle) || $showTitle): ?>
    <h1><span><?= Html::encode($this->title) ?></span></h1>
<?php endif;?>

<?= netis\utils\web\Alerts::widget() ?>


<div class="history-search">
    <?php $form = ActiveForm::begin(['method' => 'get']); ?>

    <div class="row">
        <div class="col-md-3">
            <?= $form->field($searchModel, 'request_date_from'); ?>
            <?= $form->field($searchModel, 'request_url'); ?>
            <div class="form-group">
                <?= Html::submitButton(Yii::t('app', 'Search'), ['class' => 'btn btn-primary']) ?>
                <?= Html::resetButton(Yii::t('app', 'Reset'), ['class' => 'btn btn-default']) ?>
            </div>
        </div>
        <div class="col-md-3">
            <?= $form->field($searchModel, 'request_date_to'); ?>
            <?= $form->field($searchModel, 'user_ids'); ?>
            <?= $form->field($searchModel, 'request_addr'); ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($searchModel, 'model_classes'); ?>
            <?= $form->field($searchModel, 'action_types'); ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($searchModel, 'attributes'); ?>
            <?= $form->field($searchModel, 'values'); ?>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>


<a role="button" data-toggle="collapse" href=".change-details"
   aria-expanded="false"
   aria-controls="change-details">
    <?= Yii::t('app', 'All details') ?>
</a>

<?php Pjax::begin(['id' => 'historyPjax']); ?>
<?= \yii\widgets\ListView::widget([
    'id'             => 'historyGrid',
    'dataProvider'   => $dataProvider,
    'itemView'       => '_history_entry',
    'viewParams'     => ['diff' => $diff],
]); ?>
<?php Pjax::end(); ?>
