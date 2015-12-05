<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\db\Query;

/**
 * BaseBulkAction helps implementing bulk actions, like mass assignment
 * or batch update/create using a form or importing a file.
 *
 * It is intended to be used in a NetController.
 *
 * @author jwas
 * @property string $authItemTemplate
 * @property \netis\crud\crud\ActiveController $controller
 */
abstract class BaseBulkAction extends Action implements BulkActionInterface
{
    public $authItem = false;

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if (!$this->controller instanceof \netis\crud\crud\ActiveController) {
            throw new \yii\base\InvalidConfigException('BulkAction can only be used in a controller extending \netis\crud\crud\ActiveController.');
        }
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function steps()
    {
        return [
            'prepare' => [$this, 'prepare'],
            'execute' => [$this, 'execute'],
        ];
    }

    /**
     * Executes action.
     *
     * @param $step
     *
     * @return bool whether the request parameters are valid
     * @throws InvalidConfigException
     */
    public function run($step)
    {
        if ($this->authItem !== false && $this->checkAccess) {
            call_user_func($this->checkAccess, $this->authItem);
        }

        $steps = $this->steps();
        if (!isset($steps[$step])) {
            throw new InvalidConfigException('Step is not defined in class');
        }

        if (!is_callable($steps[$step])) {
            throw new InvalidConfigException('Step should be callable');
        }

        return call_user_func($steps[$step]);
    }

    /**
     * Processes a filter form and row selection submitted via GET into a CDbCriteria object.
     * @param \yii\db\ActiveRecord $model
     * @return Query
     */
    public function getQuery($model)
    {
        return parent::getQuery($model)->andWhere([
            'in',
            $model::primaryKey(),
            self::importKey($model::primaryKey(), \Yii::$app->request->getQueryParam('keys'))
        ]);
    }

    public function getDataProvider(ActiveRecord $model, Query $query)
    {
        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
            'sort' => [
                'defaultOrder' => array_fill_keys($model->primaryKey(), SORT_ASC),
            ],
        ]);
    }

    /**
     * Renders a configuration form.
     */
    abstract public function prepare();

    /**
     * Performs bulk operations, displays progress if they can be split into batches
     * or are performed by a background worker or redirects to the post summary.
     * May also ask for confirmation as an extra failsafe.
     */
    abstract public function execute();
}
