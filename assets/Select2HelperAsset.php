<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\assets;

use maddoger\widgets\Select2BootstrapAsset;
use yii\web\AssetBundle;

/**
 * @package netis\crud\assets
 */
class Select2HelperAsset extends AssetBundle
{
    public $sourcePath = '@netis/yii2-crud/assets/js';
    public $baseUrl = '@web';

    public $js = [
        'select2helper.js',
    ];

    public $depends = [
        Select2BootstrapAsset::class,
        RelationsAsset::class,
    ];
}