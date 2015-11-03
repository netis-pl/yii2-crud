<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use netis\utils\db\ActiveQuery;
use netis\utils\db\ActiveSearchInterface;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecordInterface;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class Action extends \yii\rest\Action
{
    const COMPOSITE_KEY_SEPARATOR = ';';
    const KEYS_SEPARATOR = ',';

    /**
     * @var string[]|callable a list of fields or a PHP callable returning list of fields that will be used to
     * create columns, detail view attributes or form fields.
     *
     * The signature of the callable should be:
     *
     * ```php
     * function ($action, $context, $model) {
     *     // $action is the action object currently running
     *     // $context is one of: 'grid', 'detail', 'form', 'searchForm'
     *     // $modelClass is the AR model
     * }
     * ```
     *
     * The list should be an array of field names or field definitions.
     * If the former, the field name will be treated as an object property name whose value will be used
     * as the field value. If the latter, the array key should be the field name while the array value should be
     * the corresponding field definition which can be either an object property name or a PHP callable
     * returning the corresponding field value. The signature of the callable should be:
     *
     * ```php
     * function ($model) {
     *     // $modelClass is the AR model
     * }
     * ```
     *
     * If not set, defaults to model attributes and hasOne relations.
     *
     * @see ActiveRecord::toArray()
     */
    public $fields;

    /**
     * @var string[]|callable a list of fields or a PHP callable returning list of fields that will be used
     * as extra fields in view and update actions.
     *
     * By default, those are hasMany relations.
     * @see [[$fields]]
     */
    public $extraFields;
    /**
     * @var string view name used when rendering a HTML response, defaults to current action id
     */
    public $viewName;
    /**
     * @var ActiveQuery cached query
     */
    private $query;

    /** @var array active named queries */
    public $activeQueries = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->controller instanceof ActiveController || $this->controller instanceof \yii\rest\ActiveController) {
            if ($this->modelClass === null) {
                $this->modelClass = $this->controller->modelClass;
            }
            if ($this->checkAccess === null) {
                $this->checkAccess = [$this->controller, 'checkAccess'];
            }
        }
        if ($this->viewName === null) {
            $this->viewName = $this->id;
        }
        parent::init();
    }

    /**
     * Returns the data model based on the primary key given.
     * If the data model is not found, a 404 HTTP exception will be raised.
     * @param string $id the ID of the model to be loaded. If the model has a composite primary key,
     * the ID must be a string of the primary key values separated by commas.
     * The order of the primary key values should follow that returned by the `primaryKey()` method
     * of the model.
     * @return ActiveRecordInterface the model found
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function findModel($id)
    {
        if ($this->findModel !== null) {
            return call_user_func($this->findModel, $id, $this);
        }

        /* @var $modelClass ActiveRecordInterface */
        $modelClass = $this->modelClass;
        $model = null;
        if (($key = $this->importKey($modelClass::primaryKey(), $id)) !== false) {
            $model = $modelClass::findOne($key);
        }

        if ($model === null) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        return $model;
    }

    /**
     * Serializes the models primary key.
     * @param array|string $key
     * @return string
     */
    public static function exportKey($key)
    {
        return self::implodeEscaped(self::COMPOSITE_KEY_SEPARATOR, array_values((array)$key));
    }

    /**
     * Deserializes the model key.
     * If importing multiple keys, they can be split into an array
     * using Action::explodeKeys($inputString).
     * @param array $keyNames
     * @param array|string $key
     * @return array
     */
    public static function importKey($keyNames, $key)
    {
        if (count($keyNames) <= 1) {
            return is_array($key)
                ? array_filter(array_map(function ($k) use ($keyNames) {
                    return empty($k) ? false : [reset($keyNames) => $k];
                }, $key))
                : [reset($keyNames) => $key];
        }
        if (is_array($key)) {
            return array_filter(array_map(function ($k) use ($keyNames) {
                $values = self::explodeEscaped(self::COMPOSITE_KEY_SEPARATOR, $k);
                if (count($keyNames) === count($values)) {
                    return array_combine($keyNames, $values);
                }
                return false;
            }, $key));
        } else {
            $values = self::explodeEscaped(self::COMPOSITE_KEY_SEPARATOR, $key);
            if (count($keyNames) === count($values)) {
                return array_combine($keyNames, $values);
            }
        }
        return false;
    }

    /**
     * Joins all elements of $pieces using $glue but escaping it in the values using $escapeChar.
     * @param string $glue
     * @param string[] $pieces
     * @param string $escapeChar
     * @return string
     */
    public static function implodeEscaped($glue, $pieces, $escapeChar = '\\')
    {
        return implode($glue, array_map(function ($k) use ($glue, $escapeChar) {
            return str_replace(
                [$glue, $escapeChar],
                [$escapeChar.$glue, $escapeChar.$escapeChar],
                $k
            );
        }, $pieces));
    }

    /**
     * Joins all elements of $pieces using default glue and escape char. This method skip empty values from imploding.
     *
     * @param string[] $pieces
     * @return string
     */
    public static function implodeKeys($pieces)
    {
        return self::implodeEscaped(self::KEYS_SEPARATOR, array_filter(array_map('trim', $pieces)));
    }

    /**
     * Splits a string into elements handling an escaped delimiter.
     * @param string $delimiter
     * @param string $string
     * @param string $escapeChar
     * @return array
     */
    public static function explodeEscaped($delimiter, $string, $escapeChar = '\\')
    {
        $d = preg_quote($delimiter, "~");
        $e = preg_quote($escapeChar, "~");
        $tokens = preg_split(
            '~' . $e . '(' . $e . '|' . $d . ')(*SKIP)(*FAIL)|' . $d . '~',
            $string
        );
        return preg_replace(
            ['~' . $e . $e . '~', '~' . $e . $d . '~'],
            [$escapeChar, $delimiter],
            $tokens
        );
    }

    /**
     * Splits multiple keys string into an array using default separator and escape char. This function removes empty
     * values from result.
     *
     * @param string $string
     * @return array
     */
    public static function explodeKeys($string)
    {
        return array_filter(array_map('trim', self::explodeEscaped(self::KEYS_SEPARATOR, $string)));
    }

    /**
     * Splits a composite key string into an array using default separator and escape char.
     * @param string $string
     * @return array
     */
    public static function explodeCompositeKey($string)
    {
        return self::explodeEscaped(self::COMPOSITE_KEY_SEPARATOR, $string);
    }

    /**
     * Sets a flash message if the response format is set to Response::FORMAT_HTML.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space. If you have a normal
     * session variable using the same name, its value will be overwritten by this method.
     * @param mixed $value flash message
     * @param boolean $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     */
    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        if (Yii::$app->response->format === Response::FORMAT_HTML) {
            Yii::$app->session->setFlash($key, $value, $removeAfterAccess);
        }
    }

    /**
     * Returns all primary and foreign key column names for specified model.
     * @param ActiveRecord $model
     * @return array names of columns from primary and foreign keys
     */
    public static function getModelKeys($model)
    {
        $keys = array_map(function ($foreignKey) {
            array_shift($foreignKey);
            return array_keys($foreignKey);
        }, $model->getTableSchema()->foreignKeys);
        $keys[] = $model->primaryKey();
        return call_user_func_array('array_merge', $keys);
    }

    /**
     * Returns default fields list by combining model attributes with hasOne relations or just hasMany relations.
     * @param ActiveRecord $model
     * @param bool $extra if false, returns attributes and hasOne relations, if true, returns only hasMany relations
     * @return array default list of fields
     */
    public static function getDefaultFields($model, $extra = false)
    {
        $fields = $extra ? [] : $model->attributes();
        foreach ($model->relations() as $relation) {
            $activeRelation = $model->getRelation($relation);
            if ((!$extra && $activeRelation->multiple) || ($extra && !$activeRelation->multiple)) {
                continue;
            }
            $fields[] = $relation;
        }
        return $fields;
    }

    /**
     * Uses the $fields property to return an array with field names or definitions.
     * @param ActiveRecord $model
     * @param string $context one of: 'grid', 'detail', 'form', 'searchForm'
     * @return array
     */
    public function getFields($model, $context = null)
    {
        if (is_array($this->fields)) {
            return $this->fields;
        } elseif (is_callable($this->fields)) {
            return call_user_func($this->fields, $this, $context, $model);
        }
        return static::getDefaultFields($model);
    }

    /**
     * Uses the $extraFields property to return an array with extra field names or definitions.
     * @param ActiveRecord $model
     * @param string $context one of: 'grid', 'detail', 'form', 'searchForm'
     * @return array
     */
    public function getExtraFields($model, $context = null)
    {
        if (is_array($this->extraFields)) {
            return $this->extraFields;
        } elseif (is_callable($this->extraFields)) {
            return call_user_func($this->extraFields, $this, $context, $model);
        }
        return static::getDefaultFields($model, true);
    }

    /**
     * Sorts the fields list, field names in $order take precedence in specified order.
     *
     * For example, to remove some columns and order a few others:
     * ```
     * return Action::orderFields(
     *    array_diff(Action::getDefaultFields($model), ['is_sale_order', 'number']),
     *    ['display_number', 'orderStatus'],
     *    true // keep the default order of other attributes
     * );
     * ```
     * @param array $fields field array, @see $fields
     * @param string[] $order field names
     * @param bool $stable if false, field not mentioned in $order param will be sorted alphabetically
     * @return array
     */
    public static function orderFields($fields, $order, $stable = false)
    {
        // build a names index and reindex $fields because numeric keys can be non continuous
        $names = [];
        $result = [];
        foreach ($fields as $key => $field) {
            if ($field instanceof \yii\widgets\ActiveField || is_array($field) || (!is_string($field) && is_callable($field))) {
                $names[] = $key;
                $result[$key] = $field;
            } else {
                $names[] = $field;
                $result[] = $field;
            }
        }
        $indexes = array_flip($names);
        $order = array_flip(array_reverse($order));
        uksort($result, function ($a, $b) use ($order, $names, $indexes, $stable) {
            if (!is_string($a)) {
                $akey = $a;
                $a = $names[$akey];
            } else {
                $akey = $indexes[$a];
            }
            if (!is_string($b)) {
                $bkey = $b;
                $b = $names[$bkey];
            } else {
                $bkey = $indexes[$b];
            }
            $wa = isset($order[$a]) ? $order[$a] : -1;
            $wb = isset($order[$b]) ? $order[$b] : -1;
            if ($wa === $wb) {
                return $stable ? $akey - $bkey : strcasecmp($a, $b);
            }
            return $wb - $wa;
        });
        return $result;
    }

    /**
     * Returns all special behavior attributes as two arrays: all attributes and only blameable attributes.
     * @param ActiveRecord $model
     * @return array two arrays: all behavior attributes and blameable attributes
     */
    public static function getModelBehaviorAttributes($model)
    {
        $behaviorAttributes = [];
        $blameableAttributes = [];
        foreach ($model->behaviors() as $behaviorName => $behaviorOptions) {
            if (!is_array($behaviorOptions)) {
                continue;
            }
            switch ($behaviorOptions['class']) {
                case 'netis\utils\db\SortableBehavior':
                    $behaviorAttributes[] = $behaviorOptions['attribute'];
                    break;
                case 'netis\utils\db\ToggableBehavior':
                    if (isset($behaviorOptions['disabledAttribute'])) {
                        $behaviorAttributes[] = $behaviorOptions['disabledAttribute'];
                    }
                    if (isset($behaviorOptions['enabledAttribute'])) {
                        $behaviorAttributes[] = $behaviorOptions['enabledAttribute'];
                    }
                    break;
                case 'netis\utils\db\BlameableBehavior':
                    if (isset($behaviorOptions['createdByAttribute'])) {
                        $behaviorAttributes[] = $behaviorOptions['createdByAttribute'];
                        $blameableAttributes[] = $behaviorOptions['createdByAttribute'];
                    }
                    if (isset($behaviorOptions['updatedByAttribute'])) {
                        $behaviorAttributes[] = $behaviorOptions['updatedByAttribute'];
                        $blameableAttributes[] = $behaviorOptions['updatedByAttribute'];
                    }
                    break;
                case 'netis\utils\db\TimestampBehavior':
                    if (isset($behaviorOptions['createdAtAttribute'])) {
                        $behaviorAttributes[] = $behaviorOptions['createdAtAttribute'];
                    }
                    if (isset($behaviorOptions['updatedAtAttribute'])) {
                        $behaviorAttributes[] = $behaviorOptions['updatedAtAttribute'];
                    }
                    break;
            }
        }
        return [$behaviorAttributes, $blameableAttributes];
    }

    private function getRelatedFields($relatedModel)
    {
        $relatedFields = self::getDefaultFields($relatedModel);
        if (!isset(Yii::$app->crudModelsMap[$relatedModel::className()])) {
            return $relatedFields;
        }
        $route = Yii::$app->crudModelsMap[$relatedModel::className()];

        if (($controller = Yii::$app->createController($route)) === false) {
            return $relatedFields;
        }
        list($controller, $route) = $controller;
        if (!isset(
            $controller->actionsClassMap,
            $controller->actionsClassMap['index'],
            $controller->actionsClassMap['index']['fields']
        )) {
            return $relatedFields;
        }

        $relatedFields = $controller->actionsClassMap['index']['fields'];
        if (is_callable($relatedFields)) {
            $relatedFields = call_user_func($relatedFields, $this, 'grid', $relatedModel);
        }
        return $relatedFields;
    }

    /**
     * @param Model $model
     * @param array $extraFields
     * @return array indexed by relation name, contains: model, dataProvider, columns
     */
    public function getModelRelations($model, $extraFields)
    {
        if (!$model instanceof ActiveRecord) {
            return [];
        }
        /** @var ActiveRecord $model */
        $result = [];
        foreach ($extraFields as $key => $field) {
            if (is_array($field)) {
                $result[$key] = $field;
                continue;
            } elseif (!is_string($field) && is_callable($field)) {
                $result[$key] = call_user_func($field, $model);
                continue;
            }

            $relation = $model->getRelation($field);
            if (!$relation->multiple) {
                continue;
            }

            if (!Yii::$app->user->can($relation->modelClass . '.read')) {
                continue;
            }
            /** @var ActiveRecord $relatedModel */
            $relatedModel = new $relation->modelClass;

            // add extra authorization conditions
            if ($relation->getBehavior('authorizer') !== null) {
                $relation->authorized(
                    $relatedModel,
                    $relatedModel->getCheckedRelations(),
                    Yii::$app->user->getIdentity()
                );
            }
            if (empty($relation->from)) {
                $relation->from = [$relatedModel::tableName().' t'];
            }

            $relatedFields = $this->getRelatedFields($relatedModel);
            foreach ($relatedFields as $relKey => $relField) {
                if (is_array($relField) || (!is_string($relField) && is_callable($relField))) {
                    $relAttribute = $relKey;
                } else {
                    $relAttribute = $relField;
                }
                if ($relAttribute === $relation->inverseOf) {
                    unset($relatedFields[$relKey]);
                }
            }

            $result[$field] = [
                'model' => $relatedModel,
                'dataProvider' => new ActiveDataProvider([
                    'query' => $relation,
                    'pagination' => [
                        'pageParam' => "$field-page",
                        'pageSize' => 10,
                    ],
                    'sort' => ['sortParam' => "$field-sort"],
                ]),
                'columns' => static::getRelationGridColumns($relatedModel, $relatedFields, $field, $relation),
            ];
        }

        return $result;
    }
    
    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @param string $relationName
     * @param \yii\db\ActiveQuery $relation
     * @return array grid columns
     */
    public static function getRelationGridColumns($model, $fields, $relationName, $relation)
    {
        return self::getGridColumns($model, $fields);
        /* disabled, since columns should already contain one linked to the view action
        $actionColumn = new ActionColumn();
        return array_merge([
            [
                'class'         => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
                'controller'    => Yii::$app->crudModelsMap[$model::className()],
                'template'      => '{view}',
                'buttons' => [
                    'view' => function ($url, $model, $key) use ($actionColumn) {
                        if (!Yii::$app->user->can($model::className() . '.read', ['model' => $model])) {
                            return null;
                        }

                        return $actionColumn->buttons['view']($url, $model, $key);
                    },
                ],
            ],
            / *[
                'class'         => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ],* /
        ], self::getGridColumns($model, $fields));*/
    }

    /**
     * @param ActiveRecord $model
     * @param string $field attribute name
     * @param string|array $format attribute format
     * @return array grid column definition
     */
    protected static function getAttributeColumn($model, $field, $format)
    {
        $isNumeric = in_array(is_array($format) ? reset($format) : $format, [
            'boolean', 'smallint', 'integer', 'bigint', 'float', 'decimal', 'multiplied',
            'shortWeight', 'shortLength', 'money', 'currency', 'minorCurrency',
        ]);
        if ($format === 'crudLink' || (is_array($format) && reset($format) === 'crudLink')) {
            $format = ['crudLink', ['data-pjax' => '0']];
        }
        $column = [
            'attribute' => $field,
            'format' => $format,
        ];
        if ($isNumeric) {
            $column['contentOptions'] = function ($model, $key, $index, $column) {
                return $model->{$column->attribute} === null ? [] : ['class' => 'text-right text-nowrap'];
            };
        }
        return $column;
    }

    /**
     * @param ActiveRecord $model
     * @param string $field relation name
     * @param ActiveQuery $relation
     * @return array grid column definition
     */
    protected static function getRelationColumn($model, $field, $relation)
    {
        return [
            'attribute' => $field,
            'format'    => ['crudLink', ['data-pjax' => '0']],
            'visible'   => true,
            'label'     => $model->getRelationLabel($relation, $field),
        ];
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @return array grid columns
     */
    public static function getGridColumns($model, $fields)
    {
        if (!$model instanceof ActiveRecord) {
            return $model->attributes();
        }

        /** @var ActiveRecord $model */
        list($behaviorAttributes, $blameableAttributes) = self::getModelBehaviorAttributes($model);
        $formats = $model->attributeFormats();
        $keys    = self::getModelKeys($model);

        $columns = [];
        foreach ($fields as $key => $field) {
            // for arrays and callables, don't generate the column, use the one provided
            if (is_array($field)) {
                $columns[$key] = $field;
                continue;
            } elseif (!is_string($field) && is_callable($field)) {
                $columns[$key] = call_user_func($field, $model);
                continue;
            }
            // if the field is from a relation (eg. client.firstname) treat it as an attribute
            $format = isset($formats[$field]) ? $formats[$field] : $model->getAttributeFormat($field);

            if ($format !== null) {
                if (in_array($field, $keys) || in_array($field, $behaviorAttributes)) {
                    continue;
                }
                $columns[] = static::getAttributeColumn($model, $field, $format);
                continue;
            }

            $relation = $model->getRelation($field);
            foreach ($relation->link as $left => $right) {
                if (in_array($right, $blameableAttributes)) {
                    continue 2;
                }
            }

            if (!Yii::$app->user->can($relation->modelClass . '.read')) {
                continue;
            }
            $columns[] = static::getRelationColumn($model, $field, $relation);
        }

        return $columns;
    }

    /**
     * Returns a new ActiveQuery object. Can be overridden instead of getQuery().
     * @param \yii\db\ActiveRecord $model
     * @return \yii\db\ActiveQuery
     */
    protected function createQuery($model)
    {
        return $model::find();
    }

    /**
     * Returns an ActiveQuery configured using request query params and current user identity.
     * @param \yii\db\ActiveRecord $model
     * @return \yii\db\ActiveQuery
     */
    public function getQuery($model)
    {
        if ($this->query !== null) {
            return $this->query;
        }

        $this->query = $this->createQuery($model);
        if (!empty($this->activeQueries)) {
            $this->query->setActiveQueries($this->activeQueries);
        }

        $params = Yii::$app->request->queryParams;
        if (isset($params['query']) && !isset($params['ids'])) {
            $this->query->setActiveQueries($params['query']);
        }
        if (isset($params['search']) && is_string($params['search'])) {
            $this->query->quickSearchPhrase = $params['search'];
        }

        // add extra authorization conditions
        if ($model->getBehavior('authorizer')) {
            $this->query->authorized($model, $model->getCheckedRelations(), Yii::$app->user->getIdentity());
        }

        if ($model instanceof ActiveSearchInterface) {
            $model->addConditions($this->query);
        }

        return $this->query;
    }

    /**
     * Prepares the data provider that should return the requested collection of the models.
     * @param \yii\db\ActiveRecord $model
     * @return ActiveDataProvider
     */
    protected function prepareDataProvider($model)
    {
        $query = $this->getQuery($model);

        if ($model instanceof ActiveSearchInterface) {
            return $model->search($query);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSizeLimit' => [-1, 0x7FFFFFFF],
                'defaultPageSize' => 25,
            ],
        ]);
    }

    /**
     * @return ActiveSearchInterface
     */
    public function getSearchModel()
    {
        /** @var ActiveRecord $model */
        if ($this->controller instanceof ActiveController) {
            $model = $this->controller->getSearchModel();
        } else {
            $model = new $this->modelClass();
        }

        $params = Yii::$app->request->queryParams;
        $scope = $model->formName();
        if (($scope === '' && is_array($params))
            || ($scope !== '' && isset($params[$scope]) && is_array($params[$scope]))
        ) {
            $model->load($params);
        }
        if (isset($params['ids'])) {
            $keys = Action::importKey($model::primaryKey(), Action::explodeKeys($params['ids']));
            $model->setAttributes($keys);
        }
        return $model;
    }
}
