<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\rbac;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * AuthorizerBehavior provides methods to check if a model is related to current user.
 * @package netis\utils\rbac
 */
class AuthorizerBehavior extends Behavior
{
    /**
     * @var string Name of the model that will be used to check authorization
     * against. If null, the model that this behavior is attached to will be
     * used.
     */
    public $modelClass;
    /**
     * @var integer number of seconds for how long the resolved relations array will be cached
     */
    public $cachingDuration = 0x7FFFFFFF;
    /**
     * @var string name of the cache application component to use, if null, caching will be disabled
     */
    public $cacheID = 'cache';
    /**
     * @var string
     */
    const CACHE_KEY_PREFIX = 'relationAuthorizer.resolvedRelations.';
    /**
     * @var array @see isRelated()
     */
    protected $isRelatedCache = [];

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if ($this->modelClass === null) {
            $this->modelClass = get_class($owner);
        }
        parent::attach($owner);
    }

    /**
     * @param array $relations list of model relations to check, supports dot notation for indirect relations
     * @param IdentityInterface $user if null, Yii::$app->user->identity will be used
     * @return bool
     */
    public function isRelated($relations, $user = null)
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        if ($owner->getIsNewRecord()) {
            return true;
        }
        $key = sha1(serialize($relations) . $user->getId());
        $token = !YII_DEBUG ? '' : $this->modelClass . ' (' . print_r($owner->getPrimaryKey(), true) . ') through '
            . json_encode($relations) . ' for ' . $user->getId();
        \Yii::trace('Checking access to '.$token, 'relationAuthorizer');

        if (array_key_exists($key, $this->isRelatedCache)) {
            return $this->isRelatedCache[$key];
        }

        $schema = $owner->getDb()->getSchema();
        $t = $schema->quoteSimpleTableName('t');
        $pks = $owner->getTableSchema()->primaryKey;

        $pkConditions = [];
        $pkParams = [];
        foreach ($pks as $index => $pk) {
            $pkConditions[$t.'.'.$schema->quoteSimpleColumnName($pk)] = ':pk'.$index;
            $pkParams[':pk'.$index] = $owner->$pk;
        }
        $pkConditions = 'ROW(' . implode(',', array_keys($pkConditions)) . ') '
            . '= ROW(' . implode(',', $pkConditions) . ')';

        $relationQuery = $owner->find()->getRelatedUserQuery($owner, $relations, $user, $pkConditions, $pkParams, $owner->primaryKey);
        if (!empty($relationQuery->where)) {
            $query = 'SELECT '.$owner->getDb()
                ->getQueryBuilder()
                ->buildCondition($relationQuery->where, $relationQuery->params);
            $match = $owner->getDb()
                ->createCommand($query, $relationQuery->params)
                ->queryScalar();
            if ($match) {
                \Yii::trace('Allowing access to '.$token, 'relationAuthorizer');
                return $this->isRelatedCache[$key] = true;
            } else {
                \Yii::trace('Denying access to '.$token.', not related '
                    . 'through existing relations.', 'relationAuthorizer');
                return $this->isRelatedCache[$key] = false;
            }
        }

        // model and user has no direct or indirect relation spanning at least 1 model
        \Yii::trace('Denying access to ' . $token . ', no common relations found.', 'relationAuthorizer');
        return $this->isRelatedCache[$key] = null;
    }

    /**
     * Returns values of the 'relations' data param stored in auth items
     * traversed by last call to \netis\utils\rbac\DbManager::checkAccess().
     * @return array
     */
    public function getCheckedRelations()
    {
        $authManager = \Yii::$app->getAuthManager();
        $groups = array_map(
            function ($name) use ($authManager) {
                return ($authItem = $authManager->getPermission($name)) !== null
                && isset($authItem->data['relations']) ? $authItem->data['relations'] : array();
            },
            $authManager->getCurrentPath()
        );
        return empty($groups) ? [] : call_user_func_array('array_merge', $groups);
    }
}
