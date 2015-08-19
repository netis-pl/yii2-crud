<?php

namespace netis\utils\widgets;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\widgets\InputWidget;
use yii\helpers\Url;

/**
 * Renders a list of models.
 * @package netis\utils\widgets
 */
class DocumentFiles extends InputWidget
{
    /**
     * @var string action route to read document.
     */
    public $action;
    /**
     * @var string Document model display attribute, if null, the model will be cast to string
     */
    public $displayAttribute = null;
    /**
     * @var bool should display labels be encoded
     */
    public $encodeLabels = true;
    /**
     * @var boolean should display delete button
     */
    public $deleteButton = true;
    /**
     * @var string Action route to delete document
     */
    public $deleteAction;
    /**
     * @var boolean should display fileInput
     */
    public $fileInput = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->action === null) {
            throw new InvalidConfigException(
                Yii::t('app', "The DocumentFiles widget requires the '{parameter}' parameter.", [
                    'parameter' => 'action',
                ])
            );
        } elseif (!is_array($this->getValue())) {
            throw new InvalidConfigException(
                Yii::t('app', 'The value for the DocumentFiles widget has to be an array.')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $result = [];
        /** @var \yii\db\ActiveRecord $model */
        foreach ($this->getValue() as $model) {
            $documentId = \netis\utils\crud\Action::exportKey($model->getPrimaryKey());
            $url = Url::toRoute([
                $this->action,
                'id' => $documentId,
            ]);
            $result[] = Html::tag('li', Html::a($this->getLabel($model), $url) . $this->createDeleteButton($documentId));
        }
        return Html::tag('ul', implode('', $result), ['class' => 'list-unstyled']) . $this->createFileInput();
    }

    /**
     * @return mixed
     */
    private function getValue()
    {
        if ($this->hasModel()) {
            return $this->model->{$this->attribute};
        }
        if (isset($this->options['value'])) {
            return $this->options['value'];
        }
        return null;
    }

    /**
     * @param \yii\base\Model $model
     * @return string
     */
    private function getLabel($model)
    {
        $value = $this->displayAttribute === null ? (string)$model : $model->{$this->displayAttribute};
        return $this->encodeLabels ? Html::encode($value) : $value;
    }

    /**
     * @param integer $documentId
     *
     * @return string
     */
    public function createDeleteButton($documentId)
    {
        if ($this->deleteButton === false) {
            return '';
        }
        $url = Url::toRoute([
            $this->deleteAction,
            'id' => $documentId,
        ]);

        return Html::a(Html::tag('i', '', ['class' => 'glyphicon glyphicon-trash']), $url, [
            'data-method'  => 'POST',
            'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'),
            'aria-label'   => Yii::t('app', 'Delete'),
        ]);
    }

    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function createFileInput()
    {
        if ($this->fileInput === false) {
            return '';
        }
        if (!$this->model->hasProperty('documentFile')) {
            throw new InvalidConfigException(
                Yii::t('app', "The DocumentFiles widget requires the '{property}' property.", [
                    'property' => 'documentFile',
                ])
            );
        }

        return Html::activeFileInput($this->model, 'documentFile');
    }
}
