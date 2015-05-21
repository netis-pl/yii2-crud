<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use Yii;
use yii\base\InvalidParamException;

class View extends \yii\web\View
{
    /**
     * @var string the root directory that contains view files for this module
     */
    private $_defaultViewPath;

    /**
     * Returns the directory that contains the default view files.
     * @return string the root directory of default view files.
     */
    public function getDefaultViewPath()
    {
        if ($this->_defaultViewPath !== null) {
            return $this->_defaultViewPath;
        }
        return $this->_defaultViewPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'defaultViews';
    }

    /**
     * Sets the directory that contains the default view files.
     * @param string $path the root directory of default view files.
     * @throws InvalidParamException if the directory is invalid
     */
    public function setDefaultViewPath($path)
    {
        $this->_defaultViewPath = Yii::getAlias($path);
    }

    /**
     * Changes:
     *
     * * if view file does not exist, set it to a default view
     *
     * @inheritdoc
     */
    protected function findViewFile($view, $context = null)
    {
        $path = parent::findViewFile($view, $context);

        if (strncmp($view, '@', 1) === 0 || is_file($path)) {
            return $path;
        }

        $file = $this->getDefaultViewPath() . DIRECTORY_SEPARATOR . ltrim($view, '/');

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }
        $path = $file . '.' . $this->defaultExtension;
        if ($this->defaultExtension !== 'php' && !is_file($path)) {
            $path = $file . '.php';
        }

        return $path;
    }
}
