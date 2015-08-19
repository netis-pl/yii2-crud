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
     * @var string
     */
    public $template = "{label} {deleteButton}";
    /**
     * @var string Action route to delete document
     */
    public $deleteAction;
    /**
     * @var string name of model upload attribute
     */
    public $uploadAttribute = 'documentFiles';

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
            $parts = [];
            $documentId = \netis\utils\crud\Action::exportKey($model->getPrimaryKey());
            $url = Url::toRoute([
                $this->action,
                'id' => $documentId,
            ]);
            $parts['{label}'] = Html::a($this->getLabel($model), $url);
            $parts['{deleteButton}'] = $this->createDeleteButton($documentId);
            $result[] = Html::tag('li',  strtr($this->template, $parts) );
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
        if (!$this->model->hasProperty($this->uploadAttribute)) {
            throw new InvalidConfigException(
                Yii::t('app', "The DocumentFiles widget requires the '{property}' property.", [
                    'property' => $this->uploadAttribute,
                ])
            );
        }
        $attributeName = (isset($this->options['multiple'])) ? $this->uploadAttribute . '[]' : $this->uploadAttribute;

        return Html::activeFileInput($this->model, $attributeName, $this->options);
    }
}