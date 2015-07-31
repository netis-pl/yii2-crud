<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveSearchTrait;
use netis\utils\web\Response;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\data\DataProviderInterface;
use yii\filters\AccessControl;
use yii\filters\ContentNegotiator;
use yii\web\ForbiddenHttpException;

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
     * @var string the scenario used for creating a model.
     * @see ActiveRecord::scenarios()
     */
    public $createScenario = ActiveRecord::SCENARIO_CREATE;
    /**
     * @var string the scenario used for updating a model.
     * @see ActiveRecord::scenarios()
     */
    public $updateScenario = ActiveRecord::SCENARIO_UPDATE;
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
     * An extra 'verbs' property is recognized and used only for the @see verbs() method.
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
                    'text/html' => Response::FORMAT_HTML,
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml' => Response::FORMAT_XML,
                    // custom formats
                    'text/csv' => Response::FORMAT_CSV,
                    'application/pdf' => Response::FORMAT_PDF,
                    'application/vnd.ms-excel' => Response::FORMAT_XLS,
                ],
            ],
            'authenticator' => [
                'class' => \yii\filters\auth\CompositeAuth::className(),
                'authMethods' => !Yii::$app->user->getIsGuest() || Yii::$app->response->format === Response::FORMAT_HTML
                    ? []
                    : [
                        \yii\filters\auth\HttpBasicAuth::className(),
                        \yii\filters\auth\QueryParamAuth::className(),
                    ],
            ],
            'rateLimiter' => [
                'class' => \yii\filters\RateLimiter::className(),
                'user' => Yii::$app->user->getIdentity(), // because the default doesn't autoRenew
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
                'createScenario' => $this->createScenario,
                'updateScenario' => $this->updateScenario,
            ],
            'delete' => [
                'class' => 'netis\utils\crud\DeleteAction',
            ],
            'options' => [
                'class' => 'yii\rest\OptionsAction',
            ],
        ];
        foreach ($this->actionsClassMap as $id => $action) {
            if (!isset($actions[$id])) {
                $actions[$id] = [];
            }
            if (is_string($action)) {
                $actions[$id]['class'] = $action;
            } else {
                unset($action['verbs']);
                $actions[$id] = array_merge($actions[$id], $action);
            }
        }
        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected function verbs()
    {
        $verbs = [
            'index' => ['GET', 'HEAD'],
            'view' => ['GET', 'HEAD'],
            'create' => ['GET', 'POST'], // added GET, which returns an empty model
            'update' => ['GET', 'POST', 'PUT', 'PATCH'], // added GET and POST for compatibility
            'delete' => ['POST', 'DELETE'], // added POST for compatibility
        ];
        foreach ($this->actionsClassMap as $id => $action) {
            if (is_array($action) && isset($action['verbs'])) {
                $verbs[$id] = $action['verbs'];
            }
            if (!isset($verbs[$id])) {
                $verbs[$id] = ['GET'];
            }
        }
        return $verbs;
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
        if ($result instanceof \yii\web\Response || is_string($result)) {
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

        if (($response = $this->getPdfResponse($action, $result, $params)) !== false) {
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
            ? $this->renderAjax($action->viewName, $params)
            : $this->render($action->viewName, $params);
        return parent::afterAction($action, $content);
    }

    /**
     * Returns a PDF file with rendered view if response format is PDF, boolean false otherwise.
     * Name of the view is the same as the action id.
     * @param Action $action the action just executed.
     * @param mixed $result  the action return result.
     * @param array $params
     * @return string rendered view or boolean false if response format is not HTML.
     */
    protected function getPdfResponse($action, $result, $params)
    {
        if (Yii::$app->response->format !== Response::FORMAT_PDF) {
            return false;
        }
        $headers = Yii::$app->response->getHeaders();
        if (($location = $headers->get('Location')) !== null) {
            return $this->redirect($location);
        }
        $content = Yii::$app->request->isAjax
            ? $this->renderAjax($action->viewName, $params)
            : $this->render($action->viewName, $params);
        parent::afterAction($action, $content);

        $renderer = new \mPDF(
            'pl-x', // mode
            'A4', // format
            0, // font-size
            '', // font
            12, // margin-left
            12, // margin-right
            5, // margin-top
            5, // margin-bottom
            2, // margin-header
            2, // margin-footer
            'P' // orientation
        );
        $renderer->useSubstitutions = true;
        $renderer->simpleTables = false;
        @$renderer->WriteHTML($content);

        $response = new \yii\web\Response();
        $response->setDownloadHeaders($action->id.'.pdf', 'application/pdf', true);
        $response->format = Response::FORMAT_RAW;
        $response->content = $renderer->Output('print', 'S');
        return $response;
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
        $format = Yii::$app->response->format;
        /** @var RendererStream $rendererClass */
        switch ($format) {
            case Response::FORMAT_CSV:
                $rendererClass = 'netis\\utils\\crud\\CsvRendererStream';
                break;
            case Response::FORMAT_JSON:
                $rendererClass = 'netis\\utils\\crud\\JsonRendererStream';
                break;
            case Response::FORMAT_XML:
                $rendererClass = 'netis\\utils\\crud\\XmlRendererStream';
                break;
            case Response::FORMAT_XLS:
                $rendererClass = 'netis\\utils\\crud\\XlsRendererStream';
                break;
            default:
                throw new \HttpInvalidParamException('Unsupported format requested: '.$format);
        }
        $streamName = $format.'View';
        if (!stream_wrapper_register($streamName, $rendererClass)) {
            throw new Exception('Failed to register the RenderStream wrapper.');
        }
        $rendererClass::$params = $params;
        $response = new \yii\web\Response();
        $response->setDownloadHeaders($action->id.'.'.$format, Yii::$app->response->acceptMimeType);
        $response->format = Response::FORMAT_RAW;
        $streamParams = [
            'format' => $format,
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
        if (!$this->hasAccess($action, $model, $params)) {
            throw new ForbiddenHttpException(Yii::t('app', 'Access denied.'));
        }
    }

    /**
     * Checks the privilege of the current user.
     *
     * This method should be overridden to check whether the current user has the privilege
     * to run the specified action against the specified data model.
     *
     * @param string $action the ID of the action to be executed
     * @param object $model the model to be accessed. If null, it means no specific model is being accessed.
     * @param array $params additional parameters
     * @return bool
     */
    public function hasAccess($action, $model = null, $params = [])
    {
        return Yii::$app->user->can(
            $this->modelClass.'.'.$action,
            array_merge($params, $model === null ? [] : ['model' => $model])
        );
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
