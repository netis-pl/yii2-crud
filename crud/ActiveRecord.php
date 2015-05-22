<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

class ActiveRecord extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'string' => array(
                'class' => 'netis\utils\db\StringBehavior',
            ),
            'trackable' => array(
                'class' => 'nineinchnick\audit\behaviors\TrackableBehavior',
            ),
        ];
    }

    public function __toString()
    {
        /** @var \netis\utils\db\StringBehavior */
        if (($string = $this->getBehavior('string')) !== null) {
            return implode($string->separator, $this->getAttributes($crud->attributes));
        }
        return implode('/', $this->getPrimaryKey(true));
    }
}
