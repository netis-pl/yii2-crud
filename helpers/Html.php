<?php

namespace netis\crud\helpers;

use yii\helpers\ArrayHelper;

class Html extends \yii\bootstrap\Html
{
    public static function historyEntry($title, $time, $body, $options)
    {
        $badgeIcon = ArrayHelper::getValue($options, 'badge-icon', 'fa fa-angle-double-right');
        $timeIcon = ArrayHelper::getValue($options, 'time-icon', 'fa fa-clock-o');
        $badgeClass = ArrayHelper::getValue($options, 'badge-class', 'info');

        return <<<HTML
<div class="timeline-badge $badgeClass"><i class="$badgeIcon"></i></div>
<div class="timeline-panel">
    <div class="timeline-heading"><h4 class="timeline-title">$title <small>&middot; $time</small></h4></div>
    <div class="timeline-body">$body</div>
</div>
HTML;
    }
}