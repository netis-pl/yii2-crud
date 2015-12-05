<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use yii\base\Behavior;

/**
 * SortableBehavior allows to define custom order through selected attribute.
 *
 * An `enabled()` query is available in \netis\utils\db\ActiveQuery.
 * @package netis\utils\crud
 */
class ToggableBehavior extends Behavior
{
    /**
     * @var string The name of the attribute that marks the record as enabled.
     */
    public $enabledAttribute;

    /**
     * @var string The name of the attribute that marks the record as disabled.
     */
    public $disabledAttribute;

    /**
     * Checks if current model has not been disabled (soft-deleted).
     * @return boolean null if this model does not support soft-delete
     */
    public function isEnabled()
    {
        return ($c = $this->enabledAttribute) !== null
            ? (boolean) $this->owner->$c
            : (($c = $this->disabledAttribute) !== null ? (boolean) !$this->owner->$c : null);
    }

    /**
     * Toggles or enables/disables current model.
     * @param boolean $enable if not null will force the target state, otherwise it will be toggled
     * @return boolean
     */
    public function toggle($enable = null)
    {
        /** @var \yii\db\ActiveRecord $model */
        $model = $this->owner;
        if (($c = $this->enabledAttribute) !== null) {
            $model->$c = $enable === null ? !$model->$c : $enable;
            return $model->save(false, [$c]);
        } elseif (($c = $this->disabledAttribute) !== null) {
            $model->$c = $enable === null ? !$model->$c : !$enable;
            return $model->save(false, [$c]);
        }
        return null;
    }
}
