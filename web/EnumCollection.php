<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use yii\base\ArrayAccessTrait;
use yii\base\Object;

/**
 * Class EnumCollection holds enums - associative arrays used for formatting values.
 * @package netis\utils\web
 */
class EnumCollection extends Object implements \IteratorAggregate, \ArrayAccess, \Countable
{
    use ArrayAccessTrait;

    /**
     * @var array Each array element represents one enum (associative array).
     */
    public $data = [];

    /**
     * Returns the enum.
     * @param string $name the name of the enum to return
     * @param mixed $default the value to return in case the enum does not exist
     * @return array the requested enum.
     */
    public function get($name, $default = null)
    {
        return !isset($this->data[$name]) ? $default : $this->data[$name];
    }

    /**
     * Adds a new enum.
     * If there is already an enum with the same name, it will be replaced.
     * @param string $name the name of the enum
     * @param array $value the value of the enum
     * @return static the collection object itself
     */
    public function set($name, $value = [])
    {
        $this->data[$name] = (array) $value;

        return $this;
    }

    /**
     * Returns a value indicating whether the enum exists.
     * @param string $name the name of the enum
     * @return boolean whether the enum exists
     */
    public function has($name)
    {
        return is_string($name) || is_numeric($name) && isset($this->data[$name]);
    }

    /**
     * Removes an enum.
     * @param string $name the name of the enum to be removed.
     * @return array the value of the removed enum. Null is returned if the enum does not exist.
     */
    public function remove($name)
    {
        if (!isset($this->data[$name])) {
            return null;
        }
        $value = $this->data[$name];
        unset($this->data[$name]);
        return $value;
    }
}
