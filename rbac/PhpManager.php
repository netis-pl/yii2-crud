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
class PhpManager extends \yii\rbac\PhpManager implements IAuthManager
{
    use AuthManagerTrait;
}
