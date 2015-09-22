<?php
namespace netis\utils\crud;

use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecordInterface;
use yii\db\Query;

/**
 * BaseBulkAction helps implementing bulk actions, like mass assignment
 * or batch update/create using a form or importing a file.
 *
 * It is intended to be used in a NetController.
 *
 * @author jwas
 * @property string $authItemTemplate
 * @property \netis\utils\crud\ActiveController $controller
 */
abstract class BaseBulkAction extends Action implements BulkActionInterface
{
    public $authAction = false;

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if (!$this->controller instanceof \netis\utils\crud\ActiveController) {
            throw new \yii\base\InvalidConfigException('BulkAction can only be used in a controller extending \netis\utils\crud\ActiveController.');
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
            'runBatch' => [$this, 'runBatch'],
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
        if ($this->authAction !== false && $this->checkAccess) {
            call_user_func($this->checkAccess, $this->authAction);
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
     * @return Query
     */
    public function getQuery()
    {
        /* @var $modelClass ActiveRecordInterface */
        $modelClass = $this->modelClass;
        $query = $modelClass::find()->where([
            'in',
            $modelClass::primaryKey(),
            self::importKey($modelClass::primaryKey(), \Yii::$app->request->getQueryParam('keys'))
        ]);
        return $query;
    }

    public function getDataProvider(ActiveRecord $model, Query $query)
    {
        $defaultOrder = null;
        foreach ($model->primaryKey() as $column) {
            $defaultOrder[$column] = SORT_ASC;
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
            'sort' => [
                'defaultOrder' => $defaultOrder,
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
    abstract public function runBatch();
}
