<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Behavior;

/**
 * ActiveNavigation provides method to build navigation items for a CRUD controller.
 * Those include breadcrumbs and context menus.
 * @package netis\utils\crud
 */
class ActiveNavigation extends Behavior
{
    /**
     * @param Action $action
     * @param ActiveRecord $model
     * @return array
     */
    public function getBreadcrumbs(Action $action, $model)
    {
        $breadcrumbs = [];
        $id = $model === null || $model->isNewRecord ? null : $action->exportKey($model->getPrimaryKey(true));

        if ($action->id == 'index') {
            $breadcrumbs[] = $model->getCrudLabel();
        }
        if ($action->id == 'update') {
            $breadcrumbs[] = [
                'label' => $model->getCrudLabel('index'),
                'url' => ['index'],
            ];
            if (!$model->isNewRecord) {
                $breadcrumbs[] = [
                    'label' => $model->__toString(),
                    'url' => ['view', 'id' => $id],
                ];
                $breadcrumbs[] = Yii::t('app', 'Update');
            } else {
                $breadcrumbs[] = $model->getCrudLabel('create');
            }
        }
        if ($action->id == 'view') {
            $breadcrumbs[] = [
                'label' => $model->getCrudLabel('index'),
                'url' => ['index'],
            ];
            $breadcrumbs[] = $model->__toString();
        }
        return $breadcrumbs;
    }

    /**
     * @param Action $action
     * @param ActiveRecord $model
     * @param bool $horizontal
     * @param array $privs
     * @param array $defaultActions
     * @param array $confirms
     * @return array
     */
    public function getMenuCommon(Action $action, $model, $horizontal, $privs, $defaultActions, $confirms)
    {
        $menu = [];

        if ($privs['common']['read'] && $defaultActions['index']) {
            if ($horizontal || $action->id != 'index') {
                // drawn in horizontal menu or in update and view actions
                $menu['index'] = [
                    'label'       => Yii::t('app', 'List'),
                    'icon'        => 'list-alt',
                    'url'         => ['index'],
                    'linkOptions' => $action->id === 'update' ? ['confirm' => $confirms['leave']] : [],
                    'active'      => $action->id === 'index',
                ];
            }
        }
        if ($privs['common']['create']) {
            // drawn in all actions, that is: index, update, view
            $menu['update'] = [
                'label'       => Yii::t('app', 'Create'),
                'icon'        => 'file',
                'url'         => ['update'],
                'linkOptions' => $action->id !== 'update' ? [] : ['confirm' => $confirms['leave']],
                'active'      => $action->id === 'update' && $model->isNewRecord,
            ];
        }
        if ($privs['common']['read'] && $defaultActions['help']) {
            if ($horizontal || $action->id !== 'help') {
                $menu['help'] = [
                    'label'  => Yii::t('app', 'Help'),
                    'icon'   => 'question-sign',
                    'url'    => ['help'],
                    'active' => $action->id === 'help',
                ];
            }
        }
        // draw the history button at the end of common section,
        //because it will be replaced in current depending on action
        if ($privs['common']['read'] && $defaultActions['history'] && ($action->id === 'index')
            && $model->hasTrigger()
        ) {
            // drawn only in index action
            $menu['history'] = [
                'label'  => Yii::t('app', 'History of changes'),
                'icon'   => 'list-alt',
                'url'    => ['history'],
                'active' => $action->id === 'history',
            ];
        }
        return $menu;
    }

    /**
     * @param Action $action
     * @param ActiveRecord $model
     * @param bool $horizontal
     * @param array $privs
     * @param array $defaultActions
     * @param array $confirms
     * @return array
     */
    public function getMenuCurrent(Action $action, $model, $horizontal, $privs, $defaultActions, $confirms)
    {
        $menu = [];
        $id = $model->isNewRecord ? null : $action->exportKey($model->getPrimaryKey(true));

        if ($privs['current']['read'] && $defaultActions['history']
            && (!$model->isNewRecord || $action->id === 'update')
            && $model->hasTrigger()
        ) {
            $menu['history'] = [
                'label' => Yii::t('app', 'History of changes'),
                'icon'  => 'list-alt',
                'url'   => ['history', 'id' => $id],
            ];
        }
        if (!$horizontal && $model->isNewRecord) {
            return $menu;
        }
        if ($privs['current']['update'] && ($horizontal || $action->id !== 'update')) {
            $menu['update'] = [
                'label'  => Yii::t('app', 'Update'),
                'icon'   => 'pencil',
                'url'    => ['update', 'id' => $id],
                'active' => $action->id === 'update' && !$model->isNewRecord,
            ];
        }
        if ($privs['current']['read'] && $defaultActions['view'] && ($horizontal || $action->id !== 'view')) {
            $menu['view'] = [
                'label'       => Yii::t('app', 'View'),
                'icon'        => 'eye-open',
                'url'         => ['view', 'id' => $id],
                'linkOptions' => $action->id === 'view' ? [] : ['confirm' => $confirms['leave']],
                'active'      => $action->id === 'view',
            ];
        }
        if ($privs['current']['delete']) {
            $menu['delete'] = [
                'label'       => Yii::t('app', 'Delete'),
                'icon'        => 'trash',
                'url'         => ['delete', 'id' => $id],
                'linkOptions' => ['confirm' => $confirms['delete'], 'method' => 'post'],
            ];
        }
        if ($privs['current']['toggle']) {
            $enabled = !$model->isNewRecord && $model->getIsEnabled();
            $menu['toggle'] = [
                'label'       => $enabled ? Yii::t('app', 'Disable') : Yii::t('app', 'Enable'),
                'icon'        => $enabled ? 'ban' : 'reply',
                'url'         => ['toggle', 'id' => $id],
                'linkOptions' => $enabled ? ['confirm' => $confirms['disable']] : [],
            ];
        }
        if (class_exists('netis\fsm\components\StateAction') && $model instanceof \netis\fsm\components\IStateful
            && $privs['current']['state']
        ) {
            $transitions = $model->getTransitionsGroupedByTarget();
            $stateAttribute = $model->getStateAttributeName();
            $menu['state'] = \netis\fsm\components\StateAction::getContextMenuItem(
                'state',
                $transitions,
                $model,
                $model->$stateAttribute,
                [$this->owner, 'checkAccess']
            );
            if ($action->id === 'state') {
                $menu['state']['active'] = true;
            }
        }
        foreach ($menu as $key => $item) {
            if ($model->isNewRecord) {
                $menu[$key]['disabled'] = true;
            }
        }
        return $menu;
    }

    /**
     * Builds navigation items like the sidebar menu.
     * @param Action       $action
     * @param ActiveRecord $model
     * @param boolean      $readOnly   should the method generate links for create/update/delete actions
     * @param boolean $horizontal if menu will be displayed as horizontal pills,
     *                            in that case group titles are not added
     * @return array
     */
    public function getMenu(Action $action, ActiveRecord $model, $readOnly = false, $horizontal = true)
    {
        $menu = [
            'common' => [],
            'current' => [],
        ];

        $defaultActions = $this->owner->actions();
        // set default indexes to avoid many isset() calls later
        $defaultActions = array_merge([
            'index'  => false, 'view' => false, 'update' => false, 'delete' => false,
            'toggle' => false, 'history' => false, 'help' => false, 'state' => false,
        ], $defaultActions);

        $privs = [
            'common' => [
                'create' => !$readOnly && $defaultActions['update'] && $this->owner->hasAccess('create'),
                'read' => ($defaultActions['index'] || $defaultActions['history']) && $this->owner->hasAccess('index'),
            ],
            'current' => [
                'read' => ($defaultActions['view'] || $defaultActions['history']) && $this->owner->hasAccess('read', $model),
                'update' => !$readOnly && $defaultActions['update'] && $this->owner->hasAccess('update', $model),
                'delete' => !$readOnly && $defaultActions['delete'] && $this->owner->hasAccess('delete', $model),
                'toggle' => !$readOnly && $defaultActions['toggle'] && $this->owner->hasAccess('toggle', $model),
                'state' => !$readOnly && $defaultActions['state'] && $this->owner->hasAccess('state', $model),
            ],
        ];
        $confirms = [
            'leave' => Yii::t('app', 'Are you sure you want to leave this page? Unsaved changes will be discarded.'),
            'delete' => Yii::t('app', 'Are you sure you want to delete this item?'),
            'disable' => Yii::t('app', 'Are you sure you want to disable this item?'),
        ];

        $menu['common'] = $this->owner->getMenuCommon($action, $model, $horizontal, $privs, $defaultActions, $confirms);
        $menu['current'] = $this->owner->getMenuCurrent($action, $model, $horizontal, $privs, $defaultActions, $confirms);


        return $this->processMenu($menu, $horizontal);
    }

    /**
     * Processess a menu array from an intermediate format to one supported by the Menu widget.
     * @param array $menu
     * @param bool $horizontal
     * @return array
     */
    protected function processMenu($menu, $horizontal)
    {
        $result = [];
        foreach ($menu as $section => $items) {
            if (!$horizontal) {
                switch ($section) {
                    default:
                    case 'common':
                        $result[$section] = ['label' => Yii::t('app', 'Common')];
                        break;
                    case 'current':
                        $result[] = ['label' => Yii::t('app', 'Current')];
                        break;
                }
            }
            foreach ($items as $key => $item) {
                $isActive = isset($item['active']) && $item['active'];
                $isDisabled = isset($item['disabled']) && $item['disabled'];
                if ($isActive || $isDisabled) {
                    $item['url'] = '#';
                    if ($isDisabled) {
                        \yii\helpers\Html::addCssClass($item['options'], 'disabled');
                    }
                    if (isset($item['linkOptions']) && isset($item['linkOptions']['confirm'])
                    ) {
                        unset($item['linkOptions']['confirm']);
                    }
                }
                if (isset($item['icon'])) {
                    $item['label'] = '<i class="glyphicon glyphicon-'.$item['icon'].'"></i> '
                        . \yii\helpers\Html::encode($item['label']);
                    $item['encode'] = false;
                    unset($item['icon']);
                }
                $result[$section . '-' . $key] = $item;
            }
        }
        return $result;
    }
}
