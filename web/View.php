<?php
/**
 * @link      http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\web;

use Yii;
use yii\base\InvalidParamException;
use yii\helpers\FileHelper;

class View extends \yii\web\View
{
    /**
     * @var array|string default view path
     */
    public $defaultPath;
    /**
     * @var array the view files currently being rendered. There may be multiple view files being
     * rendered at a moment because one view may be rendered within another.
     */
    private $viewFiles = [];

    public function init()
    {
        parent::init();
        if (empty($this->defaultPath)) {
            $this->defaultPath[] = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'defaultViews');
        }
    }

    /**
     * Renders a default view.
     *
     * @param string $view    the view name.
     * @param array  $params  the parameters (name-value pairs) that will be extracted and made available in the view
     *                        file.
     * @param object $context the context to be assigned to the view and can later be accessed via [[context]]
     *                        in the view. If the context implements [[ViewContextInterface]], it may also be used to
     *                        locate the view file corresponding to a relative view name.
     *
     * @return string the rendering result
     * @throws InvalidParamException if the view cannot be resolved or the view file does not exist.
     * @see renderFile()
     */
    public function renderDefault($view, $params = [], $context = null)
    {
        //find default view file
        $file = $this->findDefaultViewFile($view, $this);

        return $this->renderFile($file, $params, $context);
    }

    public function findDefaultViewFile($view, $context = null)
    {
        foreach ((array)$this->defaultPath as $path) {
            $to   = FileHelper::normalizePath(Yii::getAlias($path)) . DIRECTORY_SEPARATOR;
            $file = $to . $view . '.' . $this->defaultExtension;

            if ($this->defaultExtension !== 'php' && !is_file($path)) {
                $file = $to . $view . '.php';
            }

            if (is_file($file) && !in_array($file, $this->viewFiles)) {
                return $file;
            }
        }

        return parent::findViewFile($view, $context);
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

        if (!is_file($path)) {
            //find default view file
            return $this->findDefaultViewFile($view, $context);
        }

        return FileHelper::localize($path);
    }

    public function beforeRender($viewFile, $params)
    {
        $result = parent::beforeRender($viewFile, $params);

        if (!$result) {
            return false;
        }

        $this->viewFiles[] = $viewFile;
        return $result;
    }

    /**
     * This method is invoked right after [[renderFile()]] renders a view file.
     * The default implementation will trigger the [[EVENT_AFTER_RENDER]] event.
     * If you override this method, make sure you call the parent implementation first.
     * @param string $viewFile the view file being rendered.
     * @param array $params the parameter array passed to the [[render()]] method.
     * @param string $output the rendering result of the view file. Updates to this parameter
     * will be passed back and returned by [[renderFile()]].
     */
    public function afterRender($viewFile, $params, &$output)
    {
        parent::afterRender($viewFile, $params, $output);
        array_pop($this->viewFiles);
    }
}
