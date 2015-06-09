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
 * * properly supports composite primary keys @todo this requires overloading yii\rest\Action::findModel() and others
 *
 * @todo probably add a custom auth method used when html format is selected
 *
 * To stream the result, instead of serializing it and using a response formatter
 * a stream wrapper is created, which gradually renders the response.
 *
 * @package netis\utils\crud
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
                $actions[$id]['class'] = $this->actionsClassMap[$id];
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

        // render a view template for HTML response format
        if (Yii::$app->response->format === Response::FORMAT_HTML) {
            $headers = Yii::$app->response->getHeaders();
            if (($location = $headers->get('Location')) !== null) {
                return $this->redirect($location);
            }
            $content = Yii::$app->request->isAjax
                ? $this->renderPartial($action->id, $params)
                : $this->render($action->id, $params);
            return parent::afterAction($action, $content);
        }

        // use serializer for all results except large data providers
        $dataProvider = null;
        if ($result instanceof DataProviderInterface) {
            $dataProvider = $result;
        } elseif (isset($result['dataProvider']) && $result['dataProvider'] instanceof DataProviderInterface) {
            $dataProvider = $result['dataProvider'];
        }
        if ($dataProvider === null
            || ($dataProvider->getPagination() === false
                && $dataProvider->getTotalCount() < self::SERIALIZATION_LIMIT)
            || $dataProvider->getPagination()->getPageSize() < self::SERIALIZATION_LIMIT
        ) {
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

        // use a renderer stream for large data providers
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

    // {{{ Navigation


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
            if ($model->isNewRecord) {
                $menu['history']['disabled'] = true;
            }
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
            if ($model->isNewRecord) {
                $menu['update']['disabled'] = true;
            }
        }
        if ($privs['current']['read'] && $defaultActions['view'] && ($horizontal || $action->id !== 'view')) {
            $menu['view'] = [
                'label'       => Yii::t('app', 'View'),
                'icon'        => 'eye-open',
                'url'         => ['view', 'id' => $id],
                'linkOptions' => $action->id === 'view' ? [] : ['confirm' => $confirms['leave']],
                'active'      => $action->id === 'view',
            ];
            if ($model->isNewRecord) {
                $menu['view']['disabled'] = true;
            }
        }
        if ($privs['current']['delete']) {
            $menu['delete'] = [
                'label'       => Yii::t('app', 'Delete'),
                'icon'        => 'trash',
                'url'         => ['delete', 'id' => $id],
                'linkOptions' => ['confirm' => $confirms['delete'], 'method' => 'post'],
            ];
            if ($model->isNewRecord) {
                $menu['delete']['disabled'] = true;
            }
        }
        if ($privs['current']['toggle']) {
            $enabled = !$model->isNewRecord && $model->getIsEnabled();
            $menu['toggle'] = [
                'label'       => $enabled ? Yii::t('app', 'Disable') : Yii::t('app', 'Enable'),
                'icon'        => $enabled ? 'ban' : 'reply',
                'url'         => ['toggle', 'id' => $id],
                'linkOptions' => $enabled ? ['confirm' => $confirms['disable']] : [],
            ];
            if ($model->isNewRecord) {
                $menu['toggle']['disabled'] = true;
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

        $defaultActions = $this->actions();
        // set default indexes to avoid many isset() calls later
        $defaultActions = array_merge([
            'index'  => false, 'view' => false, 'update' => false, 'delete' => false,
            'toggle' => false, 'history' => false, 'help' => false,
        ], $defaultActions);

        $privs = [
            'common' => [
                'create' => !$readOnly && $defaultActions['update'] && $this->checkAccess('create'),
                'read' => ($defaultActions['index'] || $defaultActions['history']) && $this->checkAccess('index'),
            ],
            'current' => [
                'read' => ($defaultActions['view'] || $defaultActions['history']) && $this->checkAccess('read', $model),
                'update' => !$readOnly && $defaultActions['update'] && $this->checkAccess('update', $model),
                'delete' => !$readOnly && $defaultActions['delete'] && $this->checkAccess('delete', $model),
                'toggle' => !$readOnly && $defaultActions['toggle'] && $this->checkAccess('toggle', $model),
            ],
        ];
        $confirms = [
            'leave' => Yii::t('app', 'Are you sure you want to leave this page? Unsaved changes will be discarded.'),
            'delete' => Yii::t('app', 'Are you sure you want to delete this item?'),
            'disable' => Yii::t('app', 'Are you sure you want to disable this item?'),
        ];

        $menu['common'] = $this->getMenuCommon($action, $model, $horizontal, $privs, $defaultActions, $confirms);
        $menu['current'] = $this->getMenuCurrent($action, $model, $horizontal, $privs, $defaultActions, $confirms);


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
                if ((isset($item['active']) && $item['active']) || (isset($item['disabled']) && $item['disabled'])) {
                    $item['url'] = '';
                    if (isset($item['linkOptions']) && isset($item['linkOptions']['confirm'])
                    ) {
                        unset($item['linkOptions']['confirm']);
                    }
                }
                $result[$section . '-' . $key] = $item;
            }
        }
        return $result;
    }

    // }}}
}
