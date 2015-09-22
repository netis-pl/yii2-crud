<?php
/**
 * Created by PhpStorm.
 * User: net
 * Date: 18.09.15
 * Time: 11:58
 */

namespace netis\utils\rbac;

trait AuthManagerTrait
{

    /**
     * @var array a list of auth items between the one checked and the one assigned to the user,
     * after a successful checkAccess() call.
     */
    protected $currentPath = [];

    /**
     * Returns a list of auth items between the one checked and the one assigned to the user,
     * after a successful checkAccess() call.
     * @return array
     */
    public function getCurrentPath()
    {
        return $this->currentPath;
    }

    /**
     * This method is only used in \netis\utils\web\User.can() when loading cached results.
     * @param array $path
     * @return $this
     */
    public function setCurrentPath($path)
    {
        $this->currentPath = $path;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $this->currentPath = [];
        return parent::checkAccess($userId, $permissionName, $params);
    }

    /**
     * @inheritdoc
     */
    protected function checkAccessFromCache($user, $itemName, $params, $assignments)
    {
        if (parent::checkAccessFromCache($user, $itemName, $params, $assignments)) {
            $this->currentPath[] = $itemName;
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function checkAccessRecursive($user, $itemName, $params, $assignments)
    {
        if (parent::checkAccessRecursive($user, $itemName, $params, $assignments)) {
            $this->currentPath[] = $itemName;
            return true;
        }
        return false;
    }
}