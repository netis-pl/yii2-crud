<?php

namespace netis\utils\widgets;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\widgets\InputWidget;

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
            $url = \yii\helpers\Url::toRoute([
                $this->action,
                'id' => \netis\utils\crud\Action::exportKey($model->getPrimaryKey()),
            ]);
            $result[] = Html::tag('li', Html::a($this->getLabel($model), $url));
        }

        return Html::tag('ul', implode('', $result), ['class' => 'list-unstyled']);
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
}
