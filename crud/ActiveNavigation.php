<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

use netis\crud\db\ActiveQuery;
use netis\crud\db\ActiveRecord;
use netis\fsm\components\StateActionInterface;
use Yii;
use yii\base\Behavior;

/**
 * ActiveNavigation provides method to build navigation items for a CRUD controller.
 * Those include breadcrumbs and context menus.
 * @package netis\crud\crud
 */
class ActiveNavigation extends Behavior
{
    const SECT_COMMON = 'common';
    const SECT_CURRENT = 'current';

    /**
     * @var bool Whether to create drop down menu for transitions.
     */
    public $useDropDownMenuForTransitions = true;
    /**
     * @var array Cached index  route
     */
    private $indexRoute;

    public $createActionId = 'update';

    public $buttons = [
        'index',
        'view',
        'print',
        'update',
        'delete',
        'toggle',
        'history',
        'help',
        'state',
    ];

    /**
     * @param \yii\base\Action $action
     * @return array
     */
    private function getIndexRoute($action)
    {
        if ($this->indexRoute !== null) {
            return $this->indexRoute;
        }

        if ($action instanceof Action) {
            $searchModel = $action->getSearchModel();
            /** @var ActiveQuery $query */
            $query = $action->getQuery($searchModel);
            if ($query instanceof ActiveQuery) {
                return $this->indexRoute = ['index', 'query' => implode(',', $query->getActiveQueries())];
            }
        }
        return $this->indexRoute = ['index'];
    }

    /**
     * @param \yii\base\Action $action
     * @param ActiveRecord $model
     * @return array
     */
    public function getBreadcrumbs(\yii\base\Action $action, $model)
    {
        $breadcrumbs = [];
        $id = null;
        if ($model !== null && !$model->isNewRecord) {
            $id = $action instanceof Action
                ? $action->exportKey($model->getPrimaryKey(true))
                : implode(';', $model->getPrimaryKey(true));
        }

        if ($action->id == 'index') {
            $breadcrumbs[] = $model->getCrudLabel('index');
        }
        if ($action->id == 'update') {
            $breadcrumbs[] = [
                'label' => $model->getCrudLabel('index'),
                'url' => $this->getIndexRoute($action),
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
        if ($action->id == 'view' || $action->id == 'print') {
            $breadcrumbs[] = [
                'label' => $model->getCrudLabel('index'),
                'url' => $this->getIndexRoute($action),
            ];
            $breadcrumbs[] = $model->__toString();
        }
        return $breadcrumbs;
    }

    /**
     * @param \yii\base\Action $action
     * @param ActiveRecord $model
     * @param bool $horizontal
     * @param array $privs
     * @param array $defaultActions
     * @param array $confirms
     * @return array
     */
    public function getMenuCommon(\yii\base\Action $action, $model, $horizontal, $privs, $defaultActions, $confirms)
    {
        $menu = [];
        $askBeforeLeave = in_array($action->id, [$this->createActionId, 'update']);

        if ($privs['read'] && $defaultActions['index'] && ($horizontal || $action->id != 'index')) {
            // drawn in horizontal menu or in update and view actions
            $menu['index'] = [
                'label'       => Yii::t('app', 'List'),
                'icon'        => 'list-alt',
                'url'         => $this->getIndexRoute($action),
                'linkOptions' => $askBeforeLeave ? ['data-confirm' => $confirms['leave']] : [],
                'active'      => $action->id === 'index',
            ];
        }
        if ($privs['create']) {
            // drawn in all actions, that is: index, update, view
            $menu[$this->createActionId] = [
                'label'       => Yii::t('app', 'Create'),
                'icon'        => 'file',
                'url'         => [$this->createActionId],
                'linkOptions' => $askBeforeLeave ? ['data-confirm' => $confirms['leave']] : [],
                'active'      => $action->id === $this->createActionId && $model->isNewRecord,
            ];
        }
        if ($privs['read'] && $defaultActions['help'] && ($horizontal || $action->id !== 'help')) {
            $menu['help'] = [
                'label'  => Yii::t('app', 'Help'),
                'icon'   => 'question-sign',
                'url'    => ['help'],
                'active' => $action->id === 'help',
            ];
        }
        // draw the history button at the end of common section,
        //because it will be replaced in current depending on action
        if ($privs['read'] && $defaultActions['history'] && in_array('index', $this->buttons)
            && $model->getBehavior('trackable') !== null
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
     * @param \yii\base\Action $action
     * @param ActiveRecord $model
     * @param bool $horizontal
     * @param array $privs
     * @param array $defaultActions
     * @param array $confirms
     * @return array
     */
    public function getMenuCurrent(\yii\base\Action $action, $model, $horizontal, $privs, $defaultActions, $confirms)
    {
        $menu = [];

        $id = null;
        if (!$model->isNewRecord) {
            $id = $action instanceof Action
                ? $action->exportKey($model->getPrimaryKey(true))
                : implode(';', $model->getPrimaryKey(true));
        }

        if ($privs['read'] && $defaultActions['history']
            && (!$model->isNewRecord || $action->id === 'update')
            && $model->getBehavior('trackable') !== null
        ) {
            $menu['history'] = [
                'label' => Yii::t('app', 'History of changes'),
                'icon'  => 'list-alt',
                'url'   => array_merge(['history', 'id' => $id], $this->getUrlParams('history', $id)),
            ];
        }
        if (!$horizontal && $model->isNewRecord) {
            return $menu;
        }
        if ($privs['update'] && ($horizontal || $action->id !== 'update')) {
            $menu['update'] = [
                'label'  => Yii::t('app', 'Update'),
                'icon'   => 'pencil',
                'url'    => array_merge(['update', 'id' => $id], $this->getUrlParams('update', $id)),
                'active' => $action->id === 'update' && !$model->isNewRecord,
            ];
        }
        if ($privs['read'] && $defaultActions['view'] && ($horizontal || $action->id !== 'view')) {
            $menu['view'] = [
                'label'       => Yii::t('app', 'View'),
                'icon'        => 'eye-open',
                'url'         => array_merge(['view', 'id' => $id], $this->getUrlParams('view', $id)),
                'linkOptions' => $action->id === 'view' ? [] : ['data-confirm' => $confirms['leave']],
                'active'      => $action->id === 'view',
            ];
        }
        if ($privs['read'] && $defaultActions['print'] && ($horizontal || $action->id !== 'print')) {
            $menu['print'] = [
                'label'       => Yii::t('app', 'Print'),
                'icon'        => 'print',
                'url'         => array_merge(['print', 'id' => $id, '_format' => 'pdf'], $this->getUrlParams('print', $id)),
                'linkOptions' => $action->id === 'print' ? [] : ['data-confirm' => $confirms['leave']],
                'active'      => $action->id === 'print',
            ];
        }
        if ($privs['delete']) {
            $menu['delete'] = [
                'label'       => Yii::t('app', 'Delete'),
                'icon'        => 'trash',
                'url'         => array_merge(['delete', 'id' => $id], $this->getUrlParams('delete', $id)),
                'linkOptions' => ['data-confirm' => $confirms['delete'], 'data-method' => 'post'],
            ];
        }
        if ($privs['toggle']) {
            $enabled = !$model->isNewRecord && $model->getIsEnabled();
            $menu['toggle'] = [
                'label'       => $enabled ? Yii::t('app', 'Disable') : Yii::t('app', 'Enable'),
                'icon'        => $enabled ? 'ban' : 'reply',
                'url'         => array_merge(['toggle', 'id' => $id], $this->getUrlParams('toggle', $id)),
                'linkOptions' => $enabled ? ['data-confirm' => $confirms['disable']] : [],
            ];
        }
        if (!$model->isNewRecord && class_exists('netis\fsm\components\StateAction')
            && $model instanceof \netis\fsm\components\IStateful
            && $privs['state']
        ) {
            $controllerActions = $this->owner->actions();
            $action = null;
            if (isset($controllerActions['state'])) {
                $action = Yii::createObject($controllerActions['state'], ['state', $this->owner]);
            }
            if ($action === null || !($action instanceof StateActionInterface)) {
                $action = Yii::createObject(\netis\fsm\components\StateAction::className(), ['state', $this->owner]);
            }
            $transitions = $model->getTransitionsGroupedByTarget();
            $stateAttribute = $model->getStateAttributeName();
            $stateMenu = $action->getContextMenuItem(
                'state',
                $transitions,
                $model,
                $model->$stateAttribute,
                [$this->owner, 'hasAccess'],
                $this->useDropDownMenuForTransitions
            );
            //if label is set then it's drop down menu
            //todo set active item for buttons
            if (isset($stateMenu['label']) && $action->id === 'state') {
                $stateMenu['active'] = true;
            }

            $menu = array_merge($menu, $stateMenu);
        }
        foreach ($menu as $key => $item) {
            if ($model->isNewRecord) {
                $menu[$key]['disabled'] = true;
            }
        }
        return $menu;
    }

    /**
     * Array of common actions. Disabled ones are returned as boolean false.
     * @return array
     */
    public function getDefaultActions()
    {
        /** @var ActiveController $owner */
        $owner = $this->owner;

        $defaultActions = $owner->actions();
        // set default indexes to avoid many isset() calls later
        $actions = [
            'index' => false,
            'view' => false,
            'print' => false,
            'update' => false,
            'delete' => false,
            'toggle' => false,
            'history' => false,
            'help' => false,
            'state' => false,
        ];
        $availableActions = array_merge($actions, $defaultActions);
        foreach ($availableActions as $action => $enabled) {
            $availableActions[$action] = $enabled && in_array($action, $this->buttons);
        }
        return $availableActions;
    }

    /**
     * @param array $defaultActions
     * @param ActiveRecord $model
     * @param array $sections   contains self::SECT_* constants, defaults to all available sections
     * @param boolean $readOnly should the method generate links for create/update/delete actions
     * @return array boolean privs for common actions grouped into sections
     */
    public function getMenuPrivileges($defaultActions, $model, $sections = null, $readOnly = false)
    {
        /** @var ActiveController $owner */
        $owner = $this->owner;

        if ($sections === null) {
            $sections = [self::SECT_COMMON, self::SECT_CURRENT];
        }

        $privileges = [];
        foreach ($sections as $section) {
            switch ($section) {
                case self::SECT_COMMON:
                    $privileges[$section] = [
                        'create' => !$readOnly && $defaultActions['update'] && $owner->hasAccess('create'),
                        'read' => ($defaultActions['index'] || $defaultActions['history']) && $owner->hasAccess('read'),
                    ];
                    break;
                case self::SECT_CURRENT:
                    $privileges[$section] = [
                        'read' => ($defaultActions['view'] || $defaultActions['print'] || $defaultActions['history'])
                            && $owner->hasAccess('read', $model),
                        'update' => !$readOnly && $defaultActions['update'] && $owner->hasAccess('update', $model),
                        'delete' => !$readOnly && $defaultActions['delete'] && $owner->hasAccess('delete', $model),
                        'toggle' => !$readOnly && $defaultActions['toggle'] && $owner->hasAccess('delete', $model),
                        'state' => !$readOnly && $defaultActions['state'] && $owner->hasAccess('update', $model),
                    ];
                    break;
            }
        }
        return $privileges;
    }

    /**
     * Builds navigation items like the sidebar menu.
     * @param \yii\base\Action       $action
     * @param ActiveRecord $model
     * @param boolean      $readOnly   should the method generate links for create/update/delete actions
     * @param boolean $horizontal if menu will be displayed as horizontal pills,
     *                            in that case group titles are not added
     * @return array
     */
    public function getMenu(\yii\base\Action $action, ActiveRecord $model, $readOnly = false, $horizontal = true)
    {
        /** @var ActiveController $owner */
        $owner = $this->owner;
        $menu = [
            self::SECT_COMMON => [],
            self::SECT_CURRENT => [],
        ];

        $defaultActions = $this->getDefaultActions();
        $privs = $this->getMenuPrivileges($defaultActions, $model, array_keys($menu), $readOnly);
        $confirms = [
            'leave' => Yii::t('app', 'Are you sure you want to leave this page? Unsaved changes will be discarded.'),
            'delete' => Yii::t('app', 'Are you sure you want to delete this item?'),
            'disable' => Yii::t('app', 'Are you sure you want to disable this item?'),
        ];

        $menu[self::SECT_COMMON] = $owner->getMenuCommon($action, $model, $horizontal, $privs[self::SECT_COMMON], $defaultActions, $confirms);
        $menu[self::SECT_CURRENT] = $owner->getMenuCurrent($action, $model, $horizontal, $privs[self::SECT_CURRENT], $defaultActions, $confirms);


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
                    case self::SECT_COMMON:
                        $result[$section] = ['label' => Yii::t('app', 'Common')];
                        break;
                    case self::SECT_CURRENT:
                        $result[] = ['label' => Yii::t('app', 'Current')];
                        break;
                }
            }
            foreach ($items as $key => $item) {
                $isActive = isset($item['active']) && $item['active'];
                $isDisabled = isset($item['disabled']) && $item['disabled'];
                if ($isActive || $isDisabled) {
                    if ($isDisabled) {
                        $item['url'] = '#';
                        \yii\helpers\Html::addCssClass($item['options'], 'disabled');
                    }
                    if (isset($item['linkOptions']) && isset($item['linkOptions']['data-confirm'])) {
                        unset($item['linkOptions']['data-confirm']);
                    }
                    if (isset($item['linkOptions']) && isset($item['linkOptions']['data-method'])) {
                        unset($item['linkOptions']['data-method']);
                    }
                }
                $encode = isset($item['encode']) ? $item['encode'] : true;
                $label = $encode ? \yii\helpers\Html::encode($item['label']) : $item['label'];
                if (isset($item['icon'])) {
                    $item['label'] = '<i class="glyphicon glyphicon-'.$item['icon'].'"></i> '. $label;
                    $item['encode'] = false;
                    unset($item['icon']);
                }
                $result[$section . '-' . $key] = $item;
            }
        }
        return $result;
    }

    /**
     * Return url params for to use in urls to actions view, update, index etc.
     *
     * @param string $actionId
     * @param string $primaryKey
     *
     * @return array
     */
    protected function getUrlParams($actionId, $primaryKey)
    {
        return [];
    }
}
