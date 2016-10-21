<?php

namespace netis\crud\widgets;

use maddoger\widgets\Select2;
use yii\base\Widget;
use yii\helpers\ArrayHelper;

/**
 * Description here...
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
class ActiveField extends \yii\bootstrap\ActiveField
{
    public function fieldOptions($options)
    {
        $this->options = ArrayHelper::merge($this->options, $options);
        return $this;
    }

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

    /**
     * Renders select2 dropdown with static items.
     *
     * @param array $items   the option data items. The array keys are option values, and the array values
     *                       are the corresponding option labels. The array can also be nested (i.e. some array values
     *                       are arrays too). For each sub-array, an option group will be generated whose label is the
     *                       key associated with the sub-array. If you have a list of data models, you may convert them
     *                       into the format described above using
     *                       [[ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     *
     * @param array $options the tag options in terms of name-value pairs.
     * @param array $clientOptions
     *
     * @return $this
     */
    public function select2($items, $options = [], $clientOptions = [])
    {
        $defaultOptions       = ['class' => 'select2', 'placeholder' => FormBuilder::getPrompt(), 'single' => true];
        $defaultClientOptions = ['width' => '100%', 'allowClear' => false, 'closeOnSelect' => true];

        return $this->widget(Select2::class, [
            'options'       => ArrayHelper::merge($defaultOptions, $options),
            'items'         => $items,
            'clientOptions' => ArrayHelper::merge($defaultClientOptions, $clientOptions),
        ]);
    }
}