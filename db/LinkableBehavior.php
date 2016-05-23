<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use netis\crud\crud\Action;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidCallException;
use yii\base\ModelEvent;
use yii\behaviors\AttributeBehavior;
use yii\db\BaseActiveRecord;
use yii\db\Expression;
use yii\db\Query;
use yii\web\ForbiddenHttpException;

/**
 * LinkableBehavior provides methods for easy linking of current model with related records.
 * @package netis\crud\db
 */
class LinkableBehavior extends Behavior
{
    /**
     * Establishes the relationship between current model and records matching passed keys.
     * Their foreign key columns are updated to the primary key value of current model.
     *
     * If the relationship involves a junction table, a new row will be inserted into the
     * junction table which contains the primary key values from both models.
     *
     * Note that this method requires that the primary key value is not null.
     *
     * If $removeKeys is null, $keys must be all records to remain associated, including those that do not change.
     *
     * @param \yii\db\ActiveQuery $relation
     * @param array $keys primary keys of the records to be linked with the current model
     * @param array $removeKeys primary keys of records to be unlinked with the current model
     * @throws InvalidCallException if the method is unable to link two models.
     */
    public function linkByKeys($relation, $keys, $removeKeys = null)
    {
        if ($relation->via !== null) {
            $this->linkJunctionByKeys($relation, $keys, $removeKeys);
        } else {
            $this->linkDirectByKeys($relation, $keys, $removeKeys);
        }
    }

    /**
     * Reestablishes links between current model and records from $relation specified by $keys.
     * Removes and inserts rows into a junction table.
     * @param \yii\db\ActiveQuery $relation
     * @param array $keys
     * @param array $removeKeys
     * @throws InvalidCallException
     */
    private function linkJunctionByKeys($relation, $keys, $removeKeys = null)
    {
        /** @var \yii\db\ActiveRecord $owner */
        $owner = $this->owner;
        if ($owner->getIsNewRecord()) {
            throw new InvalidCallException('Unable to link model: the model cannot be newly created.');
        }
        /* @var $viaRelation \yii\db\ActiveQuery */
        $viaRelation = is_array($relation->via) ? $relation->via[1] : $relation->via;
        if (is_array($relation->via)) {
            /* @var $viaClass \yii\db\ActiveRecord */
            $viaClass = $viaRelation->modelClass;
            $viaTable = $viaClass::getTableSchema()->fullName;
        } else {
            /* @var $viaTable string */
            $viaTable = reset($relation->via->from);
        }
        $schema = $owner::getDb()->getSchema();

        $this->checkAccess($relation->modelClass, array_merge($keys, $removeKeys === null ? [] : $removeKeys), 'read');

        if (!empty($keys) || !empty($removeKeys)) {
            $owner::getDb()->createCommand()
                ->delete(
                    $viaTable,
                    [
                        'and',
                        $removeKeys === null
                            ? $this->buildKeyInCondition('not in', array_values($relation->link), $keys)
                            : $this->buildKeyInCondition('in', array_values($relation->link), $removeKeys),
                        array_combine(
                            array_keys($viaRelation->link),
                            $owner->getAttributes(array_values($viaRelation->link))
                        ),
                    ]
                )->execute();
        }

        if (empty($keys)) {
            return;
        }

        /** @var \yii\db\ActiveRecord $relationClass */
        $relationClass = $relation->modelClass;

        $dirtyAttributes = [];
        if ($relation->via === null) {
            /** @var ActiveRecord $relatedModel */
            $relatedModel = new $relationClass;
            $relatedModel->loadDefaultValues();
            $event = new ModelEvent;
            //simulate inserting record so all behaviors will init attributes with default values
            $relatedModel->trigger(BaseActiveRecord::EVENT_BEFORE_INSERT, $event);
            $dirtyAttributes = array_filter($relatedModel->getDirtyAttributes());
        }
        $quotedViaTable = $schema->quoteTableName($viaTable);
        $quotedColumns = implode(', ', array_map(
            [$schema, 'quoteColumnName'],
            array_merge(
                array_keys($viaRelation->link),
                array_keys($dirtyAttributes),
                array_values($relation->link)
            )
        ));
        $prefixedPrimaryKeys = array_map(function ($c) {
            return 't.'.$c;
        }, array_keys($relation->link));
        $prefixedForeignKeys = array_map(function ($c) {
            return 'j.'.$c;
        }, array_values($relation->link));

        $alreadyLinked = $relation->select(array_keys($relation->link))->asArray()->all();
        // a subquery is used as a more SQL portable way to specify list of values by putting them in a condition
        $subquery = (new Query())
            ->select(array_merge(
                array_map(
                    function ($c) use ($schema) {
                        return '('.$schema->quoteValue($c).')';
                    },
                    $owner->getAttributes(array_values($viaRelation->link))
                ),
                array_map(
                    function ($c) use ($schema) {
                        return '('.$schema->quoteValue($c).')';
                    },
                    $dirtyAttributes
                ),
                $prefixedPrimaryKeys
            ))
            ->from($relationClass::tableName().' t')
            ->where($this->buildKeyInCondition('in', $prefixedPrimaryKeys, $keys))
            ->andWhere($this->buildKeyInCondition('not in', $prefixedPrimaryKeys, $alreadyLinked));
        if ($removeKeys === null) {
            $subquery->leftJoin($viaTable.' j', array_merge(
                array_combine(array_map(function ($c) {
                    return 'j.'.$c;
                }, array_keys($viaRelation->link)), $owner->getAttributes(array_values($viaRelation->link))),
                array_combine($prefixedForeignKeys, array_map(function ($k) {
                    return new Expression($k);
                }, $prefixedPrimaryKeys))
            ));
            $subquery->andWhere(array_fill_keys($prefixedForeignKeys, null));
        }
        list ($subquery, $params) = $owner::getDb()->getQueryBuilder()->build($subquery);
        $query = "INSERT INTO $quotedViaTable ($quotedColumns) $subquery";
        $owner::getDb()->createCommand($query, $params)->execute();
    }

    /**
     * @param \yii\db\ActiveQuery $relation
     * @param array $keys
     * @param array $removeKeys
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    private function linkDirectByKeys($relation, $keys, $removeKeys = null)
    {
        /** @var \yii\db\ActiveRecord $owner */
        $owner = $this->owner;
        // update related clearing those not in $keys and setting those which are
        /** @var \yii\db\ActiveRecord $relatedClass */
        $relatedClass = $relation->modelClass;
        $relatedTable = $relatedClass::getTableSchema()->fullName;
        $leftKeys = array_keys($relation->link);
        $rightKeys = array_values($relation->link);
        if (!empty($keys) || !empty($removeKeys)) {
            $remove = false;
            foreach (array_keys($relation->link) as $foreignKey) {
                $remove = $remove || !$relatedClass::getTableSchema()->getColumn($foreignKey)->allowNull;
            }

            $this->checkAccess($relation->modelClass, $removeKeys, $remove ? 'delete' : 'update');

            $where = [
                'and',
                $removeKeys === null
                    ? $this->buildKeyInCondition('not in', $relatedClass::getTableSchema()->primaryKey, $keys)
                    : $this->buildKeyInCondition('in', $relatedClass::getTableSchema()->primaryKey, $removeKeys),
                array_combine($leftKeys, $owner->getAttributes($rightKeys)),
            ];

            if ($remove) {
                $owner::getDb()->createCommand()->delete($relatedTable, $where)->execute();
            } else {
                $owner::getDb()->createCommand()
                    ->update($relatedTable, array_fill_keys($leftKeys, null), $where)
                    ->execute();
            }
        }
        if (empty($keys)) {
            return;
        }

        $this->checkAccess($relation->modelClass, $keys, 'update');

        $owner::getDb()->createCommand()
            ->update(
                $relatedTable,
                array_combine($leftKeys, $owner->getAttributes($rightKeys)),
                [
                    'and',
                    $this->buildKeyInCondition('in', $relatedClass::getTableSchema()->primaryKey, $keys),
                    ['not', array_combine($leftKeys, $owner->getAttributes($rightKeys))],
                ]
            )->execute();
    }

    /**
     * Reindexes $keys using $columns return valid 'in' condition like `['in', $columns, $keys]`.
     * @param string $op either 'in' or 'not in'
     * @param array $columns array of column names
     * @param array $keys array of composite values
     * @return array condition like `['in', $columns, $keys]` but with $keys reindexed by $columns
     */
    private function buildKeyInCondition($op, $columns, $keys)
    {
        return [$op, $columns, array_map(function ($k) use ($columns) {
            return array_combine($columns, $k);
        }, $keys)];
    }

    /**
     * Reads relations information from data sent by end user and uses it to link records to current model.
     * This is an equivalent to load() and save() methods.
     *
     * The data to be loaded is `$data[formName]`, where `formName` refers to the value of [[formName()]].
     * If [[formName()]] is empty, the whole `$data` array will be used.
     * Keys should be relation names. Values are either:
     * - numeric arrays containing primary key values;
     * - associative array with keys: 'add', 'remove'.
     * Warning! Data will NOT be filtered or validated in any way.
     * @param array $data the data array. This is usually `$_POST` or `$_GET`, but can also be any valid array
     * supplied by end user.
     * @param string $formName the form name to be used for loading the data into the model.
     * If not set, [[formName()]] will be used.
     * @return bool whether no data was sent or the relations has been successfully linked.
     * @throws ForbiddenHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function saveRelations($data, $formName = null)
    {
        /** @var \yii\db\ActiveRecord $owner */
        $owner = $this->owner;
        $scope = $formName === null ? $owner->formName() : $formName;
        if ($scope !== '') {
            $data = isset($data[$scope]) ? $data[$scope] : [];
        }
        foreach ($data as $relationName => $keys) {
            if (($relation = $owner->getRelation($relationName, false)) === null) {
                continue;
            }
            if (is_string($keys) && trim($keys) === '') {
                continue;
            }
            if (!is_array($keys) || isset($keys[0])) {
                $keys = is_array($keys) ? $keys : Action::explodeKeys($keys);
                $addKeys = Action::importKey($owner::primaryKey(), $keys);
                $removeKeys = null;
            } elseif (is_array($keys) && isset($keys['add']) && isset($keys['remove'])) {
                $addKeys = Action::importKey($owner::primaryKey(), Action::explodeKeys($keys['add']));
                $removeKeys = Action::importKey($owner::primaryKey(), Action::explodeKeys($keys['remove']));
            } else {
                throw new InvalidCallException('Relation keys must be either a string, a numeric array or an array with \'add\' and \'remove\' keys.');
                continue;
            }

            $this->linkByKeys($relation, $addKeys, $removeKeys);
        }
        return true;
    }

    /**
     * Checks access for multiple models.
     *
     * @param string $modelClass
     * @param array $keys
     * @param string $operation
     * @throws ForbiddenHttpException
     */
    private function checkAccess($modelClass, $keys, $operation)
    {
        $models = $modelClass::findAll($keys);
        foreach ($models as $model) {
            if (!Yii::$app->user->can("{$modelClass}.{$operation}", ['model' => $model])) {
                throw new ForbiddenHttpException(Yii::t('app', 'Access denied.'));
            }
        }
    }
}
