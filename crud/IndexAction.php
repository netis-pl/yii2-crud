<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveSearchTrait;
use Yii;
use yii\data\ActiveDataProvider;

class IndexAction extends Action
{
    /**
     * @var callable a PHP callable that will be called to prepare a data provider that
     * should return a collection of the models. If not set, [[prepareDataProvider()]] will be used instead.
     * The signature of the callable should be:
     *
     * ```php
     * function ($action) {
     *     // $action is the action object currently running
     * }
     * ```
     *
     * The callable should return an instance of [[ActiveDataProvider]].
     */
    public $prepareDataProvider;


    /**
     * @return ActiveDataProvider
     */
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        return $this->prepareDataProvider();
    }

    /**
     * Prepares the data provider that should return the requested collection of the models.
     * @return ActiveDataProvider
     */
    protected function prepareDataProvider()
    {
        if ($this->prepareDataProvider !== null) {
            return call_user_func($this->prepareDataProvider, $this);
        }

        if ($this->controller instanceof ActiveController && $this->controller->searchModelClass !== null) {
            /** @var ActiveSearchTrait $searchModel */
            $searchModel = new $this->controller->searchModelClass();
            return $searchModel->search(Yii::$app->request->queryParams);
        }
        /** @var $modelClass \yii\db\BaseActiveRecord */
        $modelClass = $this->modelClass;
        /** @var ActiveQuery $query */
        $query = $modelClass::find();
        if ($query instanceof ActiveQuery) {
            $query->defaultOrder();
        }

        return new ActiveDataProvider(['query' => $query]);
    }
}
