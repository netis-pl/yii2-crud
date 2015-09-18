<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\rbac;

/**
 * Class DbManager tracks traversed path in the auth item tree.
 * @package netis\utils\rbac
 */
class DbManager extends \yii\rbac\DbManager implements TraceableAuthManager
{
    use AuthManagerTrait;
}
