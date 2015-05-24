<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

/**
 * @inheritdoc
 * @package netis\utils\db
 */
class BlameableBehavior extends \yii\behaviors\BlameableBehavior
{

    /**
     * @var string the attribute that is filled by the users with notes during an update
     * There is no special behavior associated with this attribute.
     */
    public $updateNotesAttribute = 'update_reason';
}
