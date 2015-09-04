<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use Yii;
use yii\base\InvalidParamException;
use yii\base\ViewContextInterface;
use yii\helpers\FileHelper;

class View extends \yii\web\View implements ViewContextInterface
{
    /**
     * @var string the root directory that contains view files for this module
     */
    private $viewPath;

    /**
     * Returns the directory that contains the default view files.
     * @return string the root directory of default view files.
     */
    public function getViewPath()
    {
        if ($this->viewPath !== null) {
            return $this->viewPath;
        }

        return $this->viewPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'defaultViews');
    }

    /**
     * Sets the directory that contains the default view files.
     * @param string $path the root directory of default view files.
     * @throws InvalidParamException if the directory is invalid
     */
    public function setViewPath($path)
    {
        $this->viewPath = Yii::getAlias($path);
    }

    /**
     * Renders a default view.
     *
     * @param string $view the view name.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param object $context the context to be assigned to the view and can later be accessed via [[context]]
     * in the view. If the context implements [[ViewContextInterface]], it may also be used to locate
     * the view file corresponding to a relative view name.
     * @return string the rendering result
     * @throws InvalidParamException if the view cannot be resolved or the view file does not exist.
     * @see renderFile()
     */
    public function renderDefault($view, $params = [], $context = null)
    {
        //find default view file
        $file = parent::findViewFile($view, $this);
        return $this->renderFile($file, $params, $context);
    }

    /**
     * Changes:
     *
     * * if view file does not exist, find view file using $this as context.
     *
     * @inheritdoc
     */
    protected function findViewFile($view, $context = null)
    {
        $path = parent::findViewFile($view, $context);

        if ($this->theme !== null) {
            $path = $this->theme->applyTo($path);
        }
        if (is_file($path)) {
            $path = FileHelper::localize($path);
        }

        if (is_file($path)) {
            return $path;
        }

        return parent::findViewFile($view, $this);
    }

}
