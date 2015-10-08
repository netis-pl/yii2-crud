<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use netis\rbac\DbManager;
use yii\web\IdentityInterface;

/**
 * @inheritdoc
 * When caching is enabled, caches also auth items path if \netis\rbac\DbManager is used.
 * @package netis\utils\web
 */
class User extends \yii\web\User
{
    private $access = [];
    private $path = [];
    private $hasCustomManager = false;

    /**
     * Initializes the application component.
     */
    public function init()
    {
        parent::init();
        $this->hasCustomManager = $this->getAuthManager() instanceof DbManager;
    }

    /**
     * @inheritdoc
     */
    public function setIdentity($identity)
    {
        parent::setIdentity($identity);
        if ($identity instanceof IdentityInterface) {
            $this->access = [];
            $this->path = [];
        }
    }

    /**
     * @inheritdoc
     */
    public function can($permissionName, $params = [], $allowCaching = true)
    {
        /** @var DbManager $manager */
        $manager = $this->getAuthManager();
        if ($allowCaching && empty($params) && isset($this->access[$permissionName])) {
            if ($this->hasCustomManager) {
                $manager->setCurrentPath($this->path[$permissionName]);
            }
            return $this->access[$permissionName];
        }
        $access = $manager->checkAccess($this->getId(), $permissionName, $params);
        if ($allowCaching && empty($params)) {
            $this->access[$permissionName] = $access;
            if ($this->hasCustomManager) {
                $this->path[$permissionName] = $manager->getCurrentPath();
            }
        }

        return $access;
    }
}
