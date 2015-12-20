<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\web;

use yii\base\ViewRenderer;
use yii\caching\Cache;
use yii\caching\FileDependency;
use yii\di\Instance;
use yii\helpers\Markdown;

class MdViewRenderer extends ViewRenderer
{
    const CACHE_PREFIX = 'mdViewRenderer.';
    /**
     * @var Cache|array|string the cache object or the application component ID of the cache object.
     * The rendered output will be stored using this cache object.
     *
     * After the MdViewRenderer object is created, if you want to change this property,
     * you should only assign it with a cache object.
     */
    public $cache = 'cache';
    /**
     * @var string the markdown flavor to use. Defaults to `original`.
     * @see \yii\helpers\Markdown::$flavors
     */
    public $flavor = 'original';

    public function init()
    {
        if ($this->cache) {
            $this->cache = Instance::ensure($this->cache, Cache::className());
        }
    }

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException when params are not empty
     */
    public function render($view, $file, $params)
    {
        if (!empty($params)) {
            throw new \InvalidArgumentException('MdViewRenderer does not support params.');
        }
        if ($this->cache) {
            $key = self::CACHE_PREFIX . $file;
            $result = $this->cache->get($key);
            if ($result === false) {
                $result = Markdown::process(file_get_contents($file), $this->flavor);
                $this->cache->set($key, $result, 0, (new FileDependency(['fileName' => $file])));
            }
        } else {
            $result = Markdown::process(file_get_contents($file), $this->flavor);
        }
        return $result;
    }

}
