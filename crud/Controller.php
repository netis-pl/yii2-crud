<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\crud;

use Yii;
use yii\db\ActiveRecord;
use netis\crud\db\ActiveSearchInterface;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

/**
 * Class Controller
 * @package netis\utils\crud
 */
class Controller extends \yii\web\Controller
{
    /**
     * @var string name of the ActiveRecord class for which this controller provides a CRUD interface.
     */
    public $modelClass;
    /**
     * @var string name of the search class, if null defaults to 'NAMESPACE\search\MODEL_CLASS'.
     */
    public $searchModelClass;

    public function init()
    {
        parent::init();
        if ($this->searchModelClass === null) {
            $parts = explode('\\', $this->modelClass);
            $modelClass = array_pop($parts);
            $namespace = implode('\\', $parts);
            $this->searchModelClass = $namespace . '\\search\\' . $modelClass;
        }
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Product models.
     * @return mixed
     */
    public function actionIndex()
    {
        /** @var ActiveSearchInterface $searchModel */
        $searchModel = new $this->searchModelClass();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single AR model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Updates an existing AR model or creates a new one.
     * If update or creation is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id = null)
    {
        if ($id === null) {
            /** @var ActiveRecord $model */
            $model = new $this->modelClass();
        } else {
            $model = $this->findModel($id);
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->primaryKey]);
        }
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing AR model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the AR model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ActiveRecord the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $modelClass = $this->modelClass;
        if (($model = $modelClass::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
