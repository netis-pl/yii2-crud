<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\rbac;

/**
 * Checks if model from params is associated with current user through relations specified in data.
 * Supported options set through the data property:
 * * relations array, support indirect relations using a dot as a separator
 * * modelParamName string, optional, defaults to 'model'
 * * allowEmpty bool, optional, is the model param required, defaults to false
 */
class RelationsRule extends \yii\rbac\Rule
{
    public $name = 'relations';

    /**
     * @param string|integer $user the user ID.
     * @param \yii\rbac\Item $item the role or permission that this rule is associated with
     * @param array $params parameters passed to ManagerInterface::checkAccess().
     * @return boolean a value indicating whether the rule permits the role or permission it is associated with.
     */
    public function execute($user, $item, $params)
    {
        if (\Yii::$app->user->isGuest) {
            return false;
        }
        $relations = isset($item->data['relations']) ? $item->data['relations'] : [];
        $allowEmpty = isset($item->data['allowEmpty']) ? $item->data['allowEmpty'] : false;
        return ($allowEmpty && !isset($params['model'])) || $params['model']->isRelated($relations);
    }
}
