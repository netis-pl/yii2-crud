<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

class ActiveRecord extends \yii\db\ActiveRecord
{
    public function __toString()
    {
        if (($crud = $this->getBehavior('crud')) !== null) {
            return implode($crud['pkSeparator'], $this->getAttributes((array)$this->$crud['representingColumn']));
        }
        return implode('/', $this->getPrimaryKey(true));
    }
}
