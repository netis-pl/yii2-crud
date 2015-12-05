<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

use yii\base\ArrayAccessTrait;
use yii\base\Component;

/**
 * Class ModelsMap holds model to controller mappings.
 * @package netis\crud\crud
 */
class ModelsMap extends Component implements \IteratorAggregate, \ArrayAccess, \Countable
{
    use ArrayAccessTrait;

    /**
     * @var array Each array element represents one model class to controller mapping.
     */
    public $data = [];

    /**
     * Returns the controller class matching model class.
     * @param string $name the name of the model class
     * @param mixed $default the value to return in case the model class is not registered
     * @return array controller class for requested model class.
     */
    public function get($name, $default = null)
    {
        return !isset($this->data[$name]) ? $default : $this->data[$name];
    }

    /**
     * Adds a new mapping.
     * If there is already a mapping for this model class, it will be replaced.
     * @param string $name the name of the model class
     * @param array $value controller class name
     * @return static the collection object itself
     */
    public function set($name, $value = [])
    {
        $this->data[$name] = (array) $value;

        return $this;
    }

    /**
     * Returns a value indicating whether a mapping exists for this model class name.
     * @param string $name the name of the model class
     * @return boolean whether the mapping exists
     */
    public function has($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Removes a mapping.
     * @param string $name the name of the model class to be removed.
     * @return array the value of the removed mapping. Null is returned if the mapping does not exist.
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
