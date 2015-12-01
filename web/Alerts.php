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
     * @var bool if true, alerts can be dismissed
     */
    public $dismissible = true;

    /**
     * Renders the widget.
     */
    public function run()
    {
        $flashMessages = Yii::$app->session->getAllFlashes();
        if (empty($flashMessages)) {
            return;
        }
        $closeLabel = Yii::t('app', 'Close');
        $baseCss = 'alert';
        if ($this->dismissible) {
            $baseCss .= ' alert-dismissible';
            $dismissButton = <<<HTML
<button type="button" class="close" data-dismiss="alert" aria-label="$closeLabel">
    <span aria-hidden="true">&times;</span>
</button>
HTML;
        }
        echo '<div class="flashes">';
        foreach ($flashMessages as $key => $messages) {
            $cssClasses = $baseCss . ' alert-'.(isset($this->map[$key]) ? $this->map[$key] : $key);
            if (!is_array($messages)) {
                $messages = [$messages];
            }
            foreach ($messages as $message) {
                echo '<div class="'.$cssClasses.'" role="alert">'.$dismissButton.$message.'</div>';
            }
        }
        echo '</div>';
    }
}
