<?php

use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model ActiveRecord */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $controller netis\utils\crud\ActiveController */

$controller = $this->context;

if ($model instanceof \netis\utils\crud\ActiveRecord) {
    $this->title                 = $model->getCrudLabel('relation');
    $this->params['breadcrumbs'] = [
        [
            'label' => $model->getCrudLabel('index'),
            'url' => ['index'],
        ],
        [
            'label' => $model->__toString(),
            'url' => ['view', 'id' => $id],
        ],
        Yii::t('app', 'History'),
    ];
    $this->params['menu'] = $controller->getMenu($controller->action, $model);
} else {
    $this->title = Yii::t('app', 'History');
}

$this->registerCss(file_get_contents(Yii::getAlias('@vendor/phpspec/php-diff/example/styles.css')));
$styles = <<<CSS
.changeset {
    border: 1px solid gray;
    padding: 0.5em 1em;
    margin-bottom: 2em;
}
CSS;

$this->registerCss($styles);
?>

<h1><span><?= Html::encode($this->title) ?></span></h1>
<?= netis\utils\web\Alerts::widget() ?>

<?php Pjax::begin(['id' => 'historyPjax']); ?>
<?= \yii\widgets\ListView::widget([
    'id'             => 'historyGrid',
    'dataProvider'   => $dataProvider,
    'itemView'       => '_history_entry',
]); ?>
<?php Pjax::end(); ?>
