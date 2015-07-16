<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\rbac;

use yii\base\Behavior;
use yii\base\InvalidCallException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\web\IdentityInterface;

/**
 * AuthorizerQueryBehavior provides a query to fetch models related to current user.
 * @package netis\utils\rbac
 */
class AuthorizerQueryBehavior extends Behavior
{
    /**
     * @param ActiveRecord $model     must have the AuthorizerBehavior attached
     * @param array $relations        list of model relations to check, supports dot notation for indirect relations
     * @param IdentityInterface $user if null, Yii::$app->user->identity will be used
     * @return ActiveQuery
     */
    public function authorized($model, $relations, $user = null)
    {
        /** @var ActiveQuery $owner */
        $owner = $this->owner;
        $query = $this->getRelatedUserQuery($model, $relations, $user);
        $owner->andWhere($query->where);
        $owner->addParams($query->params);
        return $owner;
    }

    /**
     * @param string $alias
     * @param string|array $columns
     * @param \yii\db\Schema $schema
     * @return string
     */
    protected function quoteColumn($alias, $columns, $schema)
    {
        $t = $schema->quoteSimpleTableName($alias);
        if (!is_array($columns)) {
            return $t . '.' . $schema->quoteSimpleColumnName($columns);
        }
        $result = array();
        foreach ($columns as $column) {
            $result[] = $t.'.'.$schema->quoteSimpleColumnName($column);
        }
        return implode(',', $result);
    }

    /**
     * Joins conditions obtained from getCompositeRelatedConditions into one condition using a subquery.
     * @param ActiveRecord $model     must have the AuthorizerBehavior attached
     * @param array $relations        list of model relations to check, supports dot notation for indirect relations
     * @param IdentityInterface $user if null, Yii::$app->user->identity will be used
     * @param array $baseConditions base conditions passed down to getCompositeRelatedCriteria()
     * @param array $baseParams base params passed down to getCompositeRelatedCriteria()
     * @param mixed $primaryKey a scalar value used in the condition, if null, an SQL expression is used instead;
     *                          composite keys (array) are supported, but order must be retained
     * @return Query
     */
    public function getRelatedUserQuery($model, $relations, $user = null, $baseConditions = [], $baseParams = [], $primaryKey = null)
    {
        if ($user === null) {
            $user = \Yii::$app->user->identity;
        }

        $query = new Query;
        $tableSchema = $model->getTableSchema();

        if ($primaryKey === null) {
            $pkExpression = $this->quoteColumn('t', $tableSchema->primaryKey, $model->getDb()->getSchema());
            if (count($tableSchema->primaryKey) > 1) {
                $pkExpression = "ROW($pkExpression)";
            }
        } elseif (!is_array($primaryKey)) {
            $pkExpression = ':relationAuthorizer_pk';
            $query->params[':relationAuthorizer_pk'] = $primaryKey;
        } else {
            if (($key = key($primaryKey)) !== null && !is_numeric($key)) {
                // sort primaryKey by $tableSchema->primaryKey
                $keys = array_flip($tableSchema->primaryKey);
                uksort(
                    $primaryKey,
                    function ($a, $b) use ($keys) {
                        return $keys[$a] - $keys[$b];
                    }
                );
            }
            $pkExpression = [];
            foreach (array_values($primaryKey) as $index => $pk) {
                $pkExpression[] = ':relationAuthorizer_pk'.$index;
                $query->params[':relationAuthorizer_pk'.$index] = $pk;
            }
            $pkExpression = 'ROW('.implode(',', $pkExpression).')';
        }

        /** @var ActiveQuery[] $relationQueries */
        $relationQueries = $this->getCompositeRelatedUserQuery($model, $relations, $user, $baseConditions, $baseParams);
        $conditions = ['OR'];
        foreach ($relationQueries as $relationQuery) {
            if (empty($relationQuery->where)
                || (!empty($baseConditions) && $relationQuery->where === $baseConditions)
            ) {
                continue;
            }
            $relationQuery->select($this->quoteColumn('t', $tableSchema->primaryKey, $model->getDb()->getSchema()));
            $command = $relationQuery->createCommand($model->getDb());
            $conditions[] = $pkExpression.' IN ('.$command->getSql().')';
            $query->params = array_merge($query->params, $relationQuery->params);
        }
        if ($conditions !== ['OR']) {
            $query->where = $conditions;
        }
        return $query;
    }

    /**
     * Returns queries that contain necessary joins and condition
     * to select only those records which are related directly or indirectly
     * with the current user.
     * @param ActiveRecord $model     must have the AuthorizerBehavior attached
     * @param array $relations        list of model relations to check, supports dot notation for indirect relations
     * @param IdentityInterface $user if null, Yii::$app->user->identity will be used
     * @param array $baseConditions
     * @param array $baseParams
     * @return ActiveQuery[]
     */
    public function getCompositeRelatedUserQuery($model, array $relations, $user, $baseConditions = [], $baseParams = [])
    {
        $schema = $model->getDb()->getSchema();
        $userPk = array_map([$schema, 'quoteSimpleColumnName'], $user->tableSchema->primaryKey);
        $result = [];

        if (count($userPk) > 1) {
            throw new InvalidCallException('Composite primary key in User model is not supported.');
        } else {
            $userPk = reset($userPk);
        }

        $mainQuery = $model->find();
        if (empty($mainQuery->from)) {
            $mainQuery->from = [$model->tableName().' t'];
        }
        $mainQuery->distinct = true;

        foreach ($relations as $relationName) {
            if (($pos = strpos($relationName, '.')) === false) {
                $relation = $model->getRelation($relationName);
                if (!$relation->multiple) {
                    $query = $mainQuery;
                } else {
                    $query = $model->find();
                    if (empty($query->from)) {
                        $query->from = [$model->tableName().' t'];
                    }
                }
                $query->innerJoinWith([$relationName => function ($query) use ($relation, $relationName) {
                    /** @var ActiveRecord $modelClass */
                    $modelClass = $relation->modelClass;
                    return $query->from([$modelClass::tableName() . ' ' . $relationName]);
                }]);
                $column = $schema->quoteSimpleTableName($relationName).'.'.$userPk;
                $query->orWhere($column. ' IS NOT NULL AND ' . $column . ' = :current_user_id');
                $query->addParams([':current_user_id' => $user->getId()]);
                if ($relation->multiple) {
                    $query->andWhere($baseConditions, $baseParams);
                    $result[] = $query;
                }
            } else {
                $userRelationName = substr($relationName, $pos + 1);
                $relationName = substr($relationName, 0, $pos);
                $relation = $model->getRelation($relationName);
                /** @var ActiveRecord $relationModel */
                $relationModel = new $relation->modelClass;
                $userRelation = $relationModel->getRelation($userRelationName);

                $userQuery = $relationModel->find();
                if (empty($userQuery->from)) {
                    $userQuery->from = [$relationModel->tableName().' t'];
                }
                $userQuery->distinct();
                $userQuery->select($this->quoteColumn('t', $relationModel->tableSchema->primaryKey, $schema));
                //$userQuery->innerJoinWith($userRelationName);
                $userQuery->innerJoinWith([$userRelationName => function ($query) use ($userRelation, $userRelationName) {
                    /** @var ActiveRecord $modelClass */
                    $modelClass = $userRelation->modelClass;
                    return $query->from([$modelClass::tableName() . ' ' . $userRelationName]);
                }]);
                $userQuery->andWhere($schema->quoteSimpleTableName($userRelationName) . '.' . $userPk
                        . ' = :current_user_id');
                $command = $userQuery->createCommand($model->getDb());

                $query = $model->find();
                if (empty($query->from)) {
                    $query->from = [$model->tableName().' t'];
                }
                $query->distinct();
                //$query->innerJoinWith($relationName);
                $query->innerJoinWith([$relationName => function ($query) use ($relation, $relationName) {
                    /** @var ActiveRecord $modelClass */
                    $modelClass = $relation->modelClass;
                    return $query->from([$modelClass::tableName() . ' ' . $relationName]);
                }]);
                $fk = $this->quoteColumn($relationName, $relationModel->tableSchema->primaryKey, $schema);
                $query->orWhere('COALESCE(' . (is_array($relationModel->tableSchema->primaryKey)
                        ? 'ROW('.$fk.')' : $fk) .' IN ('.$command->getSql().'), false)');
                $query->addParams([':current_user_id' => $user->getId()]);
                $query->andWhere($baseConditions, $baseParams);
                $result[] = $query;
            }
        }

        $mainQuery->andWhere($baseConditions, $baseParams);
        $result[] = $mainQuery;
        return $result;
    }
}
