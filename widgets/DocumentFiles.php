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
     * @param \netis\utils\crud\Action::exportKey $documentId
     *
     * @return string
     */
    public function createDeleteButton($documentId)
    {
        return Html::activeCheckbox($this->model, "$this->uploadAttribute[]", ['label' => Yii::t('app', 'Delete'), 'value' => $documentId]);
    }

    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function createFileInput()
    {
        $attributeName = (isset($this->options['multiple'])) ? $this->uploadAttribute . '[]' : $this->uploadAttribute;

        return Html::activeFileInput($this->model, $attributeName, $this->options);
    }
}
