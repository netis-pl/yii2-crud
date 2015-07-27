<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\assets;

use yii\web\AssetBundle;

/**
 * @package netis\utils\assets
 */
class RelationsAsset extends AssetBundle
{
    public $sourcePath = '@netis/yii2-utils/assets/relations';
    public $baseUrl = '@web';
    public $css = [
    ];
    public $js = [
        'relations.js',
    ];
    public $depends = [
        'yii\widgets\PjaxAsset',
        'yii\grid\GridViewAsset',
    ];
}