<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\db\ActiveRecordInterface;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class Action extends \yii\rest\Action
{
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
        return implode(',', array_values((array)$key));
    }

    /**
     * Deserializes the models primary key.
     * @param ActiveRecordInterface $modelClass
     * @param array|string $key
     * @return array
     */
    public static function importKey($modelClass, $key)
    {
        $keys = $modelClass::primaryKey();
        if (count($keys) <= 1) {
            return [reset($keys) => $key];
        }
        $values = explode(',', $key);
        if (count($keys) === count($values)) {
            return array_combine($keys, $values);
        }
        return false;
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
    protected function getModelKeys($model)
    {
        $keys = array_map(function ($foreignKey) {
            array_shift($foreignKey);
            return array_keys($foreignKey);
        }, $model->getTableSchema()->foreignKeys);
        $keys[] = $model->primaryKey();
        return array_reduce($keys, 'array_merge', []);
    }

    /**
     * Returns all special behavior attributes as two arrays: all attributes and only blameable attributes.
     * @param ActiveRecord $model
     * @return array two arrays: all behavior attributes and blameable attributes
     */
    protected function getModelBehaviorAttributes($model)
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
}
