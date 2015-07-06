<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecordInterface;
use yii\grid\ActionColumn;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class Action extends \yii\rest\Action
{
    const COMPOSITE_KEY_SEPARATOR = '-';
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
        if (($key = $this->importKey($modelClass, $id)) !== false) {
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
     * Deserializes the models primary key.
     * If importing multiple keys, they can be split into an array
     * using Action::explodeEscaped(Action::KEYS_SEPARATOR, $inputString).
     * @param ActiveRecordInterface $modelClass
     * @param array|string $key
     * @return array
     */
    public static function importKey($modelClass, $key)
    {
        $keys = $modelClass::primaryKey();
        if (count($keys) <= 1) {
            return is_array($key)
                ? array_filter(array_map(function ($k) use ($keys) {
                    return empty($k) ? false : [reset($keys) => $k];
                }, $key))
                : [reset($keys) => $key];
        }
        if (is_array($key)) {
            return array_filter(array_map(function ($k) use ($keys) {
                $values = self::explodeEscaped(self::COMPOSITE_KEY_SEPARATOR, $k);
                if (count($keys) === count($values)) {
                    return array_combine($keys, $values);
                }
                return false;
            }, $key));
        } else {
            $values = self::explodeEscaped(self::COMPOSITE_KEY_SEPARATOR, $key);
            if (count($keys) === count($values)) {
                return array_combine($keys, $values);
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
            if (is_array($field) || (!is_string($field) && is_callable($field))) {
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
            $relatedFields = self::getDefaultFields($relatedModel);
            if (isset(Yii::$app->crudModelsMap[$relatedModel::className()])) {
                $relatedController = Yii::$app->crudModelsMap[$relatedModel::className()];
                if (isset($this->controller->module->controllerMap[basename($relatedController)])) {
                    $map = $this->controller->module->controllerMap[basename($relatedController)];
                    if (isset(
                        $map['actionsClassMap'],
                        $map['actionsClassMap']['index'],
                        $map['actionsClassMap']['index']['fields']
                    )) {
                        $relatedFields = $map['actionsClassMap']['index']['fields'];
                        if (is_callable($this->fields)) {
                            $relatedFields = call_user_func($relatedFields, $this, 'grid', $relatedModel);
                        }
                    }
                }
            }
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
                'columns' => static::getRelationGridColumns($relatedModel, $relatedFields),
            ];
        }

        return $result;
    }
    
    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @return array grid columns
     */
    public static function getRelationGridColumns($model, $fields)
    {
        $actionColumn = new ActionColumn();
        return array_merge([
            [
                'class'         => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => 'column-action'],
                'controller'    => Yii::$app->crudModelsMap[$model::className()],
                'template'      => '{view}',
                'buttons' => [
                    'view' => function ($url, $model, $key) use ($actionColumn) {
                        if (!Yii::$app->user->can($model::className() . '.read')) {
                            return null;
                        }

                        return $actionColumn->buttons['view']($url, $model, $key);
                    },
                ],
            ],
            [
                'class'         => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'column-serial'],
            ],
        ], self::getGridColumns($model, $fields));
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
        $attributes = $model->attributes();

        $columns = [];
        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                $columns[$key] = $field;
                continue;
            } elseif (!is_string($field) && is_callable($field)) {
                $columns[$key] = call_user_func($field, $model);
                continue;
            }

            if (in_array($field, $attributes)) {
                if (in_array($field, $keys) || in_array($field, $behaviorAttributes)) {
                    continue;
                }
                $isNumeric = in_array($formats[$field], [
                    'boolean', 'smallint', 'integer', 'bigint', 'float', 'decimal',
                    'shortWeight', 'shortLength', 'money', 'currency', 'minorCurrency',
                ]);
                $columns[] = [
                    'attribute' => $field,
                    'format' => $formats[$field],
                    'contentOptions' => $isNumeric
                        ? function ($model, $key, $index, $column) {
                            return $model->{$column->attribute} === null ? [] : ['class' => 'text-right text-nowrap'];
                        }
                        : [],
                ];
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
            $label = $model->getRelationLabel($relation, $field);
            $columns[] = [
                'attribute' => $field,
                'format'    => 'crudLink',
                'visible'   => true,
                'label'     => $label,
            ];
        }

        return $columns;
    }
}
