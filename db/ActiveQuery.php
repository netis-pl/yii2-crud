<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\base\InvalidConfigException;

class ActiveQuery extends \yii\db\ActiveQuery
{
    /**
     * Sets default order using display order attribute, representing attributes and primary keys.
     * @return $this
     */
    public function defaultOrder()
    {
        /* @var $model \netis\utils\crud\ActiveRecord */
        $model = new $this->modelClass;
        $order = [];
        /** @var SortableBehavior $sortable */
        if (($sortable = $model->getBehavior('sortable')) !== null) {
            $order[$sortable->attribute] = SORT_ASC;
        }
        /** @var StringBehavior $string */
        if (($string = $model->getBehavior('string')) !== null && $string->attributes !== null) {
            $order = array_merge($order, array_fill_keys($string->attributes, SORT_ASC));
        }

        $order = array_merge($order, array_fill_keys($model->primaryKey(), SORT_ASC));

        $this->orderBy($order);
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
