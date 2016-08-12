<?php
/**
 * Created by PhpStorm.
 * User: michal
 * Date: 03.07.16
 * Time: 19:26
 */

namespace netis\crud\widgets;


use yii\widgets\ActiveForm;

class DummyActiveForm extends ActiveForm
{
    public $layout = 'default';

    /**
     * Initializes the widget.
     * This renders the form open tag.
     */
    public function init()
    {
        if (!isset($this->options['id'])) {
            $this->options['id'] = $this->getId();
        }
        //parent implementation echoes begin form tag but in this
        //active form we don't want to echo anything
    }

    /**
     * Runs the widget.
     * This registers the necessary javascript code and renders the form close tag.
     * @throws InvalidCallException if `beginField()` and `endField()` calls are not matching
     */
    public function run()
    {
        //parent implementation echoes end tag and attaches clientScript
        //in this active form we don't want it
    }
}