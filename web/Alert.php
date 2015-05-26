<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use Yii;

/**
 * Alerts displays flash messages.
 *
 * ~~~
 * // $this is the view object currently being used
 * echo Alerts::widget();
 * ~~~
 */
class Alerts extends \yii\base\Widget
{
    /**
     * @var array map flash message key to css class suffix
     */
    private $map = ['error' => 'danger'];

    /**
     * Renders the widget.
     */
    public function run()
    {
        $flashMessages = Yii::$app->session->getAllFlashes();
        if (empty($flashMessages)) {
            return;
        }
        echo '<div class="flashes">';
        foreach ($flashMessages as $key => $message) {
            $cssClasses = 'alert alert-'.(isset($this->map[$key]) ? $this->map[$key] : $key);
            echo '<div class="'.$cssClasses.'">'.$message.'</div>';
        }
        echo '</div>';
    }
}
