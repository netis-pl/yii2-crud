<?php
/**
 * Application configuration for unit tests
 */
return yii\helpers\ArrayHelper::merge(
    require(YII_APP_BASE_PATH . '/config/web.php'),
    require(__DIR__ . '/config.php'),
    [

    ]
);
