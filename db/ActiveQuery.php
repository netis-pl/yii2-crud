<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;

/**
 * Provides queries to add default order and support the soft delete ToggableBehavior.
 * @package netis\utils\db
 * @method ActiveQuery authorized(\yii\db\ActiveRecord $model, array $relations, \yii\web\IdentityInterface $user)
 */
class ActiveQuery extends \yii\db\ActiveQuery
{
    /**
     * @var string Quick search phrase used to prepare conditions in this query.
     * Used only to pass its value to the grid.
     */
    public $quickSearchPhrase;
    /**
     * @var array Holds values of record counts for various named queries.
     */
    private $counters = null;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'authorizer' => [
                'class' => 'netis\utils\rbac\AuthorizerQueryBehavior',
            ],
        ];
    }

    /**
     * Returns an array with default order columns, indexed by column name and with direction as value.
     * @return array column name => order direction
     */
    public function getDefaultOrderColumns()
    {
        /* @var $model \netis\utils\crud\ActiveRecord */
        $model = new $this->modelClass;
        $columns = $model->getTableSchema()->columns;
        try {
            $indexes = $model->getDb()->getSchema()->findUniqueIndexes($model->getTableSchema());
            $unique = empty($indexes) ? [] : array_flip(call_user_func_array('array_merge', $indexes));
        } catch (NotSupportedException $e) {
            $unique = null;
        }
        $order = [];
        foreach ($model->getBehaviors() as $name => $behavior) {
            if ($behavior instanceof SortableBehavior) {
                $attributes = [$behavior->attribute];
            } elseif ($behavior instanceof LabelsBehavior) {
                if ($behavior->attributes === null) {
                    continue;
                }
                $attributes = $behavior->attributes;
            } else {
                continue;
            }

            foreach ($attributes as $attribute) {
                $order[$attribute] = SORT_ASC;
                if ($unique !== null && isset($unique[$attribute]) && isset($columns[$attribute])
                    && !$columns[$attribute]->allowNull
                ) {
                    return $order;
                }
            }
        }

        $order = array_merge($order, array_fill_keys($model->primaryKey(), SORT_ASC));

        return $order;
    }

    /**
     * Returns list of named queries safe for usage by end users.
     * @return string[]
     */
    public function publicQueries()
    {
        return ['defaultOrder', 'enabled'];
    }

    /**
     * Returns list of named queries that are countable.
     * @return string[]
     */
    public function countableQueries()
    {
        return [];
    }

    public function getCounters($baseQuery = null)
    {
        if ($this->counters !== null) {
            return $this->counters;
        }
        /** @var ActiveRecord $modelClass */
        $modelClass   = $this->modelClass;
        $queryBuilder = $modelClass::getDb()->getQueryBuilder();
        if ($baseQuery === null) {
            $baseQuery = $modelClass::find();
        }
        $params = [];
        $select = [];
        foreach ($this->countableQueries() as $queryName) {
            $query     = clone $baseQuery;
            $query->$queryName();
            $params    = array_merge($params, $query->params);
            $condition = $queryBuilder->buildCondition($query->where, $params);
            $select[]  = "COUNT(id) FILTER (WHERE $condition) AS \"$queryName\"";
        }
        if (empty($select)) {
            return $this->counters = [];
        }

        // allow to modify query before calling this method, for example add auth conditions
        $baseQuery = clone $this;
        return $this->counters = $baseQuery
            ->select($select)
            ->from($modelClass::tableName())
            ->params($params)
            ->createCommand($modelClass::getDb())
            ->queryOne();
    }

    public function getCounter($queryName)
    {
        $counters = $this->getCounters();

        return $counters[$queryName];
    }

    /**
     * Sets default order using display order attribute, representing attributes and primary keys.
     * @return $this
     */
    public function defaultOrder()
    {
        $this->orderBy($this->getDefaultOrderColumns());
        return $this;
    }

    /**
     * This method is a named scope that adds criteria to select only enabled records.
     * @return $this
     * @throws InvalidConfigException
     */
    public function enabled()
    {
        /* @var $model \netis\utils\crud\ActiveRecord */
        $model = new $this->modelClass;
        /** @var ToggableBehavior $toggle */
        if (($toggle = $model->getBehavior('toggable')) === null) {
            throw new InvalidConfigException('Toggable behavior is not enabled on model '.$this->modelClass);
        }

        if (($c = $toggle->enabledAttribute) !== null) {
            $this->andWhere($c);
        } elseif (($c = $toggle->disabledAttribute) !== null) {
            $this->andWhere("NOT $c");
        }

        return $this;
    }
}
