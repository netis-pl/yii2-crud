<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;

class ActiveQuery extends \yii\db\ActiveQuery
{
    /**
     * @var string Quick search phrase used to prepare conditions in this query.
     * Used only to pass its value to the grid.
     */
    public $quickSearchPhrase;

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
