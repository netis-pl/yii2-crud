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
            'string' => [
                'class' => 'netis\utils\db\StringBehavior',
            ],
            'trackable' => [
                'class' => 'nineinchnick\audit\behaviors\TrackableBehavior',
                'auditTableName' => 'audits.'.$this->getTableSchema()->name,
            ],
        ];
    }

    public function __toString()
    {
        /** @var \netis\utils\db\StringBehavior */
        if (($string = $this->getBehavior('string')) !== null) {
            return implode($string->separator, $this->getAttributes($string->attributes));
        }
        return implode('/', $this->getPrimaryKey(true));
    }

    public static function relations()
    {
        return [];
    }
}
