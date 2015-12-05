<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use yii\db\Expression;

/**
 * @inheritdoc
 * @package netis\utils\db
 */
class TimestampBehavior extends \yii\behaviors\TimestampBehavior
{
    /**
     * @inheritdoc
     */
    protected function getValue($event)
    {
        if ($this->value instanceof Expression) {
            return $this->value;
        }
        return $this->value !== null ? call_user_func($this->value, $event) : date('Y-m-d H:i:s');
    }
}
