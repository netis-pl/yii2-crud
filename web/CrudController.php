<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use netis\utils\db\ActiveSearchTrait;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class CrudController extends Controller
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
        /** @var ActiveSearchTrait $searchModel */
        $searchModel = new $this->searchModelClass();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
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
            'searchModel' => new $this->searchModelClass(),
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new AR model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        /** @var ActiveRecord $model */
        $model = new $this->modelClass();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->primaryKey]);
        } else {
            return $this->render('create', [
                'searchModel' => new $this->searchModelClass(),
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing AR model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->primaryKey]);
        } else {
            return $this->render('update', [
                'searchModel' => new $this->searchModelClass(),
                'model' => $model,
            ]);
        }
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
