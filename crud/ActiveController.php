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
use yii\filters\AccessControl;
use yii\filters\ContentNegotiator;
use yii\web\Response;

/**
 * Modeled after yii\rest\ActiveController with the following changes:
 *
 * * supports HTML format which is the default one
 * * allows to use huge page sizes for collections and streams the result
 * * properly supports composite primary keys
 *
 * @todo probably add a custom auth method used when html format is selected
 *
 * To stream the result, instead of serializing it and using a response formatter
 * a stream wrapper is created, which gradually renders the response.
 *
 * @package netis\utils\crud
 * @method array getBreadcrumbs(Action $action, ActiveRecord $model)
 * @method array getMenuCurrent(Action $action, ActiveRecord $model, bool $horizontal, array $privs, array $defaultActions, array $confirms)
 * @method array getMenuCommon(Action $action, ActiveRecord $model, bool $horizontal, array $privs, array $defaultActions, array $confirms)
 * @method array getMenu(Action $action, ActiveRecord $model, bool $readOnly = false, bool $horizontal = true)
 */
class ActiveController extends \yii\rest\ActiveController
{
    const SERIALIZATION_LIMIT = 1000;
    /**
     * @var string|array the configuration for creating the serializer that formats the response data.
     */
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
    ];
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = true;
    /**
     * @var string Name of the search class.
     */
    public $searchModelClass;
    /**
     * @var array Maps action id to class name.
     */
    public $actionsClassMap = [];

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'formats' => [
                    'text/csv' => 'csv',
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml' => Response::FORMAT_XML,
                    'text/html' => Response::FORMAT_HTML,
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'menu' => [
                'class' => ActiveNavigation::className(),
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        $actions = [
            'index' => [
                'class' => 'netis\utils\crud\IndexAction',
            ],
            'relation' => [
                'class' => 'netis\utils\crud\RelationAction',
            ],
            'view' => [
                'class' => 'netis\utils\crud\ViewAction',
            ],
            'update' => [
                'class' => 'netis\utils\crud\UpdateAction',
                'scenario' => $this->updateScenario,
            ],
            'delete' => [
                'class' => 'netis\utils\crud\DeleteAction',
            ],
            'options' => [
                'class' => 'yii\rest\OptionsAction',
            ],
        ];
        foreach ($actions as $id => $action) {
            if (isset($this->actionsClassMap[$id])) {
                if (is_string($this->actionsClassMap[$id])) {
                    $actions[$id]['class'] = $this->actionsClassMap[$id];
                } else {
                    $actions[$id] = array_merge($actions[$id], $this->actionsClassMap[$id]);
                }
            }
        }
        return $actions;
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
     * If the response format is HTML, either performs a redirect or renders a view template.
     * If the result is or contains a data provider with pagination disabled or a large page size,
     * then a renderer stream is used.
     * In other cases, a serializer converts the response to an array.
     * No extra action is taken when the result is already a Response object.
     *
     * @param Action $action the action just executed.
     * @param mixed $result  the action return result.
     * @return mixed the processed action result.
     * @throws Exception
     * @throws \HttpInvalidParamException
     * @throws \yii\base\InvalidConfigException
     */
    public function afterAction($action, $result)
    {
        $params = [];
        if ($result instanceof Response) {
            return parent::afterAction($action, $result);
        } elseif ($result instanceof Model) {
            $params['model'] = $result;
        } elseif ($result instanceof DataProviderInterface) {
            $params['dataProvider'] = $result;
        } else {
            $params = $result;
        }

        if (($response = $this->getHtmlResponse($action, $result, $params)) !== false) {
            return $response;
        }

        if (($response = $this->getSerializedResponse($action, $result, $params)) !== false) {
            return $response;
        }

        // use a renderer stream for large data providers
        return $this->getLargeResponse($action, $result, $params);
    }

    /**
     * Returns a rendered view if response format is HTML, boolean false otherwise.
     * Name of the view is the same as the action id.
     * @param Action $action the action just executed.
     * @param mixed $result  the action return result.
     * @param array $params
     * @return string rendered view or boolean false if response format is not HTML.
     */
    protected function getHtmlResponse($action, $result, $params)
    {
        if (Yii::$app->response->format !== Response::FORMAT_HTML) {
            return false;
        }
        $headers = Yii::$app->response->getHeaders();
        if (($location = $headers->get('Location')) !== null) {
            return $this->redirect($location);
        }
        $content = Yii::$app->request->isAjax
            ? $this->renderAjax($action->id, $params)
            : $this->render($action->id, $params);
        return parent::afterAction($action, $content);
    }

    /**
     * Returns a serialized dataProvider or model if the response does NOT contain
     * a dataProvider with large pages or pagination disabled and large number of items.
     * @param Action $action the action just executed.
     * @param mixed $result  the action return result.
     * @param array $params params extracted from result
     * @return array serialized dataProvider or model or boolean false if response contains a large dataProvider.
     * @throws \yii\base\InvalidConfigException
     */
    protected function getSerializedResponse($action, $result, $params)
    {
        $dataProvider = null;
        if ($result instanceof DataProviderInterface) {
            $dataProvider = $result;
        } elseif (isset($result['dataProvider']) && $result['dataProvider'] instanceof DataProviderInterface) {
            $dataProvider = $result['dataProvider'];
        }
        if ($dataProvider !== null
            && ($dataProvider->getPagination() !== false
                || $dataProvider->getTotalCount() >= self::SERIALIZATION_LIMIT)
            && $dataProvider->getPagination()->getPageSize() >= self::SERIALIZATION_LIMIT
        ) {
            return false;
        }

        if (isset($result['dataProvider'])) {
            $data = $result['dataProvider'];
        } elseif (isset($result['model'])) {
            $data = $result['model'];
        } else {
            $data = $result;
        }
        $data = parent::afterAction($action, $data);
        return Yii::createObject($this->serializer)->serialize($data);
    }

    /**
     * Returns an open renderer stream that outputs formatted items from the dataProvider.
     * @param Action $action the action just executed.
     * @param mixed $result  the action return result.
     * @param array $params params extracted from result
     * @return Response
     * @throws Exception when failed to register the renderer stream class
     * @throws \HttpInvalidParamException
     */
    protected function getLargeResponse($action, $result, $params)
    {
        parent::afterAction($action, $result);
        switch (Yii::$app->response->format) {
            case 'csv':
                $rendererClass = 'netis\\utils\\crud\\CsvRendererStream';
                break;
            case Response::FORMAT_JSON:
                $rendererClass = 'netis\\utils\\crud\\JsonRendererStream';
                break;
            case Response::FORMAT_XML:
                $rendererClass = 'netis\\utils\\crud\\XmlRendererStream';
                break;
            default:
                throw new \HttpInvalidParamException('Unsupported format requested: '.Yii::$app->response->format);
        }
        $streamName = Yii::$app->response->format.'View';
        if (!stream_wrapper_register($streamName, $rendererClass)) {
            throw new Exception('Failed to register the RenderStream wrapper.');
        }
        $rendererClass::$params = $params;
        $response = new Response();
        $response->setDownloadHeaders($action->id.'.'.Yii::$app->response->format, Yii::$app->response->acceptMimeType);
        $response->format = Response::FORMAT_RAW;
        $streamParams = [
            'format' => Yii::$app->response->format,
            'serializer' => $this->serializer,
        ];
        $response->stream = fopen("$streamName://{$action->id}?".\http_build_query($streamParams), "r");

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

    /**
     * Checks if a help view exists for current controller.
     * @return bool
     */
    public function hasHelp()
    {
        return file_exists($this->getViewPath() . DIRECTORY_SEPARATOR . 'help' . DIRECTORY_SEPARATOR . 'index.php');
    }
}
