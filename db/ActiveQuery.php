<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

class ActiveQuery extends \yii\db\ActiveQuery
{
    public function defaultOrder()
    {
        $attributeNames = array_flip($this->primaryModel->attributes());
        $order = $this->primaryModel->primaryKey();
        /** @var StringBehavior $string */
        if (($string = $this->primaryModel->getBehavior('string')) !== null) {
            foreach ($string->attributes as $attribute) {
                if (!isset($attributeNames[$attribute])) {
                    continue;
                }
                $order[] = $attribute;
            }
        }
        if (($defaultOrderColumn = $this->primaryModel->displayOrderColumn()) !== null) {
            array_unshift($order, $defaultOrderColumn);
        }

        $this->orderBy($order);
        return $this;
    }
}
