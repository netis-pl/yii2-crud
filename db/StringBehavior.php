<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use yii\base\Behavior;
use yii\base\Model;
use yii\db\ActiveRecord;

/**
 * StringBehavior allows to configure how a model is cast to string.
 * @package netis\utils\crud
 */
class StringBehavior extends Behavior
{
    /**
     * @var array Attributes joined to form string representation.
     */
    public $attributes;
    /**
     * @var string Separator used when joining attribute values.
     */
    public $separator = ' ';

    public function init()
    {
        if ($this->attributes !== null) {
            return;
        }
        if (!($this->owner instanceof ActiveRecord)) {
            return;
        }
        /** @var ActiveRecord $model */
        $model = $this->owner;
        foreach ($model->getTableSchema()->columns as $name => $column) {
            if ($column->type == 'string' || $column->type == 'text') {
                $this->attributes = [$name];
                break;
            }
        }
        if ($this->attributes === null) {
            $this->attributes = $model->primaryKey();
        }
    }
}
