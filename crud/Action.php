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
    protected static function getModelKeys($model)
    {
        $keys = array_map(function ($foreignKey) {
            array_shift($foreignKey);
            return array_keys($foreignKey);
        }, $model->getTableSchema()->foreignKeys);
        $keys[] = $model->primaryKey();
        return call_user_func_array('array_merge', $keys);
    }

    /**
     * Returns all special behavior attributes as two arrays: all attributes and only blameable attributes.
     * @param ActiveRecord $model
     * @return array two arrays: all behavior attributes and blameable attributes
     */
    protected static function getModelBehaviorAttributes($model)
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
     * @return array indexed by relation name, contains: model, dataProvider, columns
     */
    public function getModelRelations($model)
    {
        if (!$model instanceof ActiveRecord) {
            return [];
        }
        /** @var ActiveRecord $model */
        $relations = [];
        foreach ($model->relations() as $relation) {
            $activeRelation = $model->getRelation($relation);
            if (!$activeRelation->multiple) {
                continue;
            }

            if (!Yii::$app->user->can($activeRelation->modelClass . '.read')) {
                continue;
            }

            $relatedModel = new $activeRelation->modelClass;
            $relations[$relation] = [
                'model' => $relatedModel,
                'dataProvider' => new ActiveDataProvider([
                    'query' => $activeRelation,
                    'pagination' => [
                        'pageParam' => "$relation-page",
                        'pageSize' => 10,
                    ],
                    'sort' => ['sortParam' => "$relation-sort"],
                ]),
                'columns' => static::getRelationGridColumns($relatedModel, $activeRelation->inverseOf),
            ];
        }

        return $relations;
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param string $inverseRelation
     * @return array grid columns
     */
    public static function getRelationGridColumns($model, $inverseRelation)
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
        ], self::getGridColumns($model, [$inverseRelation]));
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @param Model $model
     * @param string[] $except names of attributes or relations to be excluded
     * @return array grid columns
     */
    public static function getGridColumns($model, $except = [])
    {
        if (!$model instanceof ActiveRecord) {
            return $model->attributes();
        }

        /** @var ActiveRecord $model */
        list($behaviorAttributes, $blameableAttributes) = self::getModelBehaviorAttributes($model);
        $formats = $model->attributeFormats();
        $keys    = self::getModelKeys($model);

        $columns = [];
        foreach ($model->attributes() as $attribute) {
            if (in_array($attribute, $keys) || in_array($attribute, $behaviorAttributes)
                || in_array($attribute, $except)
            ) {
                continue;
            }
            $columns[] = $attribute . ':' . $formats[$attribute];
        }
        foreach ($model->relations() as $relation) {
            $activeRelation = $model->getRelation($relation);
            if ($activeRelation->multiple || in_array($relation, $except)) {
                continue;
            }
            foreach ($activeRelation->link as $left => $right) {
                if (in_array($right, $blameableAttributes)) {
                    continue 2;
                }
            }

            if (!Yii::$app->user->can($activeRelation->modelClass . '.read')) {
                continue;
            }
            $columns[] = [
                'attribute' => $relation,
                'format'    => 'crudLink',
                'visible'   => true,
            ];
        }

        return $columns;
    }
}
