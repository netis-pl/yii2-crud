<?php

namespace netis\crud\widgets;

use yii\base\Widget;
use yii\helpers\ArrayHelper;

/**
 * Description here...
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
class ActiveField extends \yii\bootstrap\ActiveField
{
    /**
     * @inheritdoc
     */
    public function render($content = null)
    {
        if (isset($this->parts['{input}']) && is_array($this->parts['{input}'])) {
            /** @var Widget $class */
            $class                  = ArrayHelper::remove($this->parts['{input}'], 'class');
            $this->parts['{input}'] = $class::widget($this->parts['{input}']);
        }

        return parent::render($content);
    }

    /**
     * @inheritdoc
     */
    public function staticControl($options = [])
    {
        if (!isset($options['value'])) {
            $options['value'] = (new FormBuilder(['model' => $this->model]))->fieldValue($this->attribute);
        }
        return parent::staticControl($options);
    }
}