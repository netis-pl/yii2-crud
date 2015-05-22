<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveSearchTrait;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\data\DataProviderInterface;
use yii\filters\ContentNegotiator;
use yii\web\Response;

/**
 * Modeled after yii\rest\ActiveController with the following changes:
 *
 * * supports HTML format which is the default one
 * * properly supports composite primary keys @todo this requires overloading yii\rest\Action::findModel() and others
 * * allows to use huge page sizes for collections and streams the result
 *
 * @todo probably add a custom auth method used when html format is selected
 *
 * To stream the result, instead of serializing it and using a response formatter
 * a stream wrapper is created and Response::sendStreamAsFile() is called.
 *
 * @package netis\utils\crud
 */
class ActiveController extends \yii\rest\ActiveController
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = true;
    /**
     * @var string name of the search class, if null defaults to 'NAMESPACE\search\MODEL_CLASS'.
     */
    public $searchModelClass;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'formats' => array_merge(RenderStream::formats(), ['text/html' => Response::FORMAT_HTML]),
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function verbs()
    {
        return [
            'index' => ['GET', 'HEAD'],
            'view' => ['GET', 'HEAD'],
            'create' => ['GET', 'POST'], // added GET, which returns an empty model
            'update' => ['GET', 'POST', 'PUT', 'PATCH'], // added GET and POST for compatibility
            'delete' => ['POST', 'DELETE'], // added POST for compatibility
        ];
    }

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

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        /** @var ContentNegotiator $negotiator */
        if (($negotiator = $this->getBehavior('contentNegotiator')) !== null) {
            $negotiator->negotiate();

            if (Yii::$app->response->format !== Response::FORMAT_HTML) {
                $this->enableCsrfValidation = false;
            }
        }
        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        $params = [];
        if ($result instanceof Model) {
            $params['model'] = $result;
        } elseif ($result instanceof DataProviderInterface) {
            $params['dataProvider'] = $result;
        } else {
            $params = $result;
        }
        if (Yii::$app->response->format === Response::FORMAT_HTML) {
            $headers = Yii::$app->response->getHeaders();
            if (($location = $headers->get('Location')) !== null) {
                return $this->redirect($location);
            }
            return parent::afterAction($action, $this->render($action->id, $params));
        }

        parent::afterAction($action, $result);
        if (!stream_wrapper_register("view", "netis\\utils\\crud\\RenderStream")) {
            throw new Exception('Failed to register the RenderStream wrapper.');
        }
        RenderStream::$format = Yii::$app->response->format;
        RenderStream::$params = $params;
        $response = new Response();
        $response->setDownloadHeaders($action->id.'.'.Yii::$app->response->format, Yii::$app->response->acceptMimeType);
        $response->format = Response::FORMAT_RAW;
        $response->stream = fopen("view://{$action->id}", "r");

        return $response;
    }

    /**
     * Disabled.
     * @param mixed $data the data to be serialized
     * @return mixed the serialized data.
     */
    protected function serializeData($data)
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        return Yii::$app->user->can($this->modelClass.'.'.$action, $model === null ? null : ['model' => $model]);
    }

    /**
     * @return ActiveSearchTrait
     */
    public function getSearchModel()
    {
        return new $this->searchModelClass();
    }
}
