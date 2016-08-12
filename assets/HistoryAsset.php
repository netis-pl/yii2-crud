<?php

namespace netis\crud\assets;

use yii\web\AssetBundle;

/**
 * @package netis\crud\assets
 */
class HistoryAsset extends AssetBundle
{
    public $sourcePath = '@netis/yii2-crud/assets/css';
    public $baseUrl = '@web';

    public $css = ['history.css'];
}