<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\base\Behavior;

/**
 * SortableBehavior allows to define custom order through selected attribute.
 * @package netis\utils\crud
 */
class SortableBehavior extends Behavior
{
    /**
     * @var string Attribute name that stores custom order.
     */
    public $attribute;
}
