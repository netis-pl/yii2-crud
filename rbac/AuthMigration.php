<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\rbac;

use Yii;
use yii\db\Migration;
use yii\rbac\ManagerInterface;

abstract class AuthMigration extends Migration
{
    private $labelsMap;

    /**
     * Auth items as reversed tree (child -> parents), grouped by roles.
     *
     * For example:
     * [
     *   'orders'    => [
     *     'netis\orders\models\Order.update'     => [
     *       'netis\orders\models\Order.update.own',
     *     ],
     *   ],
     * ]
     *
     * where 'orders' is a role name.
     *
     * @return array
     */
    abstract public function getMap();

    /**
     * Descriptions for roles, which are keys from getMap().
     * @return array
     */
    abstract public function getDescriptions();

    protected function getLabelsMap($modelClass)
    {
        if ($this->labelsMap !== null && isset($this->labelsMap[$modelClass])) {
            return $this->labelsMap[$modelClass];
        }

        /** @var \netis\utils\crud\ActiveRecord $model */
        $model = new $modelClass;
        if ($model instanceof \netis\utils\crud\ActiveRecord) {
            $createLabel = $model->getCrudLabel('create');
            $readLabel = $model->getCrudLabel('read');
            $updateLabel = $model->getCrudLabel('update');
            $deleteLabel = $model->getCrudLabel('delete');
        } else {
            $createLabel = 'Create ' . $modelClass;
            $readLabel   = 'Read ' . $modelClass;
            $updateLabel = 'Update ' . $modelClass;
            $deleteLabel = 'Delete ' . $modelClass;
        }

        return $this->labelsMap[$modelClass] = [
            'create' => $createLabel,
            'read' => $readLabel,
            'update' => $updateLabel,
            'delete' => $deleteLabel,
            'read.own' => $readLabel . ' - ' . Yii::t('app', 'own'),
            'update.own' => $updateLabel . ' - ' . Yii::t('app', 'own'),
            'delete.own' => $deleteLabel . ' - ' . Yii::t('app', 'own'),
            'read.related' => $readLabel . ' - ' . Yii::t('app', 'related'),
            'update.related' => $updateLabel . ' - ' . Yii::t('app', 'related'),
            'delete.related' => $deleteLabel . ' - ' . Yii::t('app', 'related'),
        ];
    }

    protected function addItem(ManagerInterface $auth, $child, $name, $parents, $role)
    {
        if (is_string($name) || is_array($name)) {
            if (is_array($name)) {
                $description = isset($name['description']) ? $name['description'] : null;
                $ruleName = isset($name['ruleName']) ? $name['ruleName'] : null;
                $data = isset($name['data']) ? $name['data'] : [];
                $name = $name['name'];
            } else {
                $description = null;
                $ruleName = null;
                $data = [];
            }
            if (($authItem = $auth->getPermission($name)) === null) {
                $authItem = $auth->createPermission($name);
                $authItem->data = $data;
                $authItem->ruleName = $ruleName;
                if ($description === null) {
                    if (strpos($name, '.') !== false) {
                        list($modelClass, $suffix) = explode('.', $name, 2);
                    } else {
                        $modelClass = $name;
                        $suffix = 'default';
                    }
                    $labelsMap   = $this->getLabelsMap($modelClass);
                    $description = $labelsMap[$suffix];
                }
                $authItem->description = $description;
                $auth->add($authItem);
            }
        } else {
            $authItem = $name;
        }
        if ($child !== null && $authItem !== null && !$auth->hasChild($authItem, $child)) {
            $auth->addChild($authItem, $child);
        }
        if (empty($parents) && !$auth->hasChild($role, $authItem)) {
            $auth->addChild($role, $authItem);
        }
        foreach ($parents as $parentName => $grandParents) {
            if (is_string($grandParents)) {
                $parentName   = $grandParents;
                $grandParents = [];
            } elseif (isset($grandParents['name']) || isset($grandParents['data']) || isset($grandParents['ruleName'])
                || isset($grandParents['description']) || isset($grandParents['parents'])
            ) {
                if (is_numeric($parentName)) {
                    $parentName = $grandParents['name'];
                }
                $grandParents['name'] = $parentName;
                $parentName   = $grandParents;
                $grandParents = isset($grandParents['parents']) ? $grandParents['parents'] : [];
            }

            $this->addItem($auth, $authItem, $parentName, $grandParents, $role);
        }
        return $authItem;
    }

    protected function removeItem(ManagerInterface $auth, $name, $parents)
    {
        if (is_string($name) || is_array($name)) {
            if (is_array($name)) {
                $name = $name['name'];
            }
            $authItem = $auth->getPermission($name);
        } else {
            $authItem = $name;
        }
        foreach ($parents as $parentName => $grantParents) {
            if (is_string($grantParents)) {
                $parentName   = $grantParents;
                $grantParents = [];
            } elseif (isset($grantParents['name']) || isset($grantParents['data']) || isset($grantParents['ruleName'])
                || isset($grantParents['parents'])
            ) {
                if (is_numeric($parentName)) {
                    $parentName = $grantParents['name'];
                }
                $grantParents['name'] = $parentName;
                $parentName   = $grantParents;
                $grantParents = isset($grantParents['parents']) ? $grantParents['parents'] : [];
            }

            $this->removeItem($auth, $parentName, $grantParents);
        }
        if ($authItem !== null) {
            $auth->remove($authItem);
        }
    }

    /**
     * Expands items from $map by adding create, read, update and delete suffixes.
     * Ignores non string items.
     * @param array $map
     * @return array
     */
    protected function expandAdminMap($map)
    {
        $result = [];
        foreach ($map as $key => $adminItem) {
            if (is_array($adminItem)) {
                $result[$key] = $adminItem;
                continue;
            }
            $result[] = $adminItem . '.create';
            $result[] = $adminItem . '.read';
            $result[] = $adminItem . '.update';
            $result[] = $adminItem . '.delete';
        }
        return $result;
    }
}
