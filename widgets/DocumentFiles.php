<?php

namespace netis\utils\widgets;

use Yii;
use yii\base\InvalidParamException;
use yii\base\Widget;
use yii\base\Model;
use yii\helpers\Html;

class DocumentFiles extends Widget
{
    /**
     * @var string action route to read document.
     */
    public $action;
    /**
     * @var Model the data model that this widget is associated with.
     */
    public $model;
    /**
     * @var string the model attribute that this widget is associated with.
     */
    public $attribute;
    /**
     * @var string Document model identifier
     */
    public $identifier = 'id';

    /**
     * @var string Document model display attribute
     */
    public $displayAttribute = 'filename';


    public function init()
    {
        if ($this->action === null) {
            throw new InvalidParamException(Yii::t('app', '{parameter} parameter has to be set.', ['parameter' => 'Action']));
        } elseif (!$this->model instanceof Model) {
            throw new InvalidParamException(Yii::t('app', 'Model has to be instance of "yii\base\Model".'));
        } elseif ($this->attribute === null) {
            throw new InvalidParamException(Yii::t('app', '{parameter} parameter has to be set.', ['parameter' => 'Attribute']));
        } elseif (!is_array($this->model->{$this->attribute})) {
            throw new InvalidParamException(Yii::t('app', 'Attribute parameter value has to be an array.'));
        }
        parent::init();
    }

    public function run()
    {
        $html = $this->prepareOutput();

        return $html;
    }

    public function prepareOutput()
    {
        $html = '<ul class="list-unstyled">';
        foreach ($this->model->{$this->attribute} as $model) {
            $html .= '<li>' . Html::a($model->{$this->displayAttribute}, \yii\helpers\Url::toRoute([
                    $this->action,
                    'id' => \netis\utils\crud\Action::exportKey($model->{$this->identifier}),
                ])) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

}
