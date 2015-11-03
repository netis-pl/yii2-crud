<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\validators;

use yii\validators\Validator;

class IntervalValidator extends Validator
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = \Yii::t('yii', 'The format of {attribute} is invalid.');
        }
    }

    /**
     * Validates a value.
     * A validator class can implement this method to support data validation out of the context of a data model.
     * @param mixed $value the data value to be validated.
     * @return array|null the error message and the parameters to be inserted into the error message.
     * Null should be returned if the data is valid.
     */
    public function validateValue($value)
    {
        try {
            new \DateInterval(strpos($value, 'P-') === 0 ? 'P' . substr($value, 2) : $value);
        } catch (\Exception $e) {
            return [$this->message, []];
        }
        return null;
    }
}
