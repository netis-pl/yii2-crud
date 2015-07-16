<?php

/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Model;

/**
 * @package netis\utils\crud
 */
class ViewAction extends Action
{
    /**
     * Displays a model.
     * @param string $id the primary key of the model.
     * @return \yii\db\ActiveRecordInterface the model being displayed
     */
    public function run($id)
    {
        /** @var ActiveRecord $model */
        $model = $this->findModel($id);
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, 'read', $model);
        }
        // this headers is used in a modal dialog when getting a redirect after saving a form
        Yii::$app->getResponse()->getHeaders()->set('X-Primary-Key', $id);
        return [
            'model'      => $model,
            'attributes' => $this->getDetailAttributes($model, $this->getFields($model, 'detail')),
            'relations'  => $this->getModelRelations($model, $this->getExtraFields($model)),
        ];
    }

    /**
     * Retrieves detail view attributes configuration using the modelClass.
     * @param Model $model
     * @param array $fields
     * @return array detail view attributes
     */
    public function getDetailAttributes($model, $fields)
    {
        if (!$model instanceof ActiveRecord) {
            return $model->attributes();
        }
        /** @var ActiveRecord $model */
        $formats = $model->attributeFormats();
        $keys    = self::getModelKeys($model);
        list($behaviorAttributes, $blameableAttributes) = self::getModelBehaviorAttributes($model);
        $attributes = $model->attributes();
        $result = [];
        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                $result[$key] = $field;
                continue;
            } elseif (!is_string($field) && is_callable($field)) {
                $result[$key] = call_user_func($field, $model);
                continue;
            }

            if (in_array($field, $attributes)) {
                if (in_array($field, $keys) || in_array($field, $blameableAttributes)) {
                    continue;
                }
                $result[$field] = [
                    'attribute' => $field,
                    'format' => $formats[$field],
                ];
                continue;
            }

            $relation = $model->getRelation($field);
            if (!$relation->multiple) {
                // validate foreign keys only for hasOne relations
                foreach ($relation->link as $left => $right) {
                    if (in_array($left, $blameableAttributes)) {
                        continue;
                    }
                }
            }

            if (!Yii::$app->user->can($relation->modelClass . '.read')) {
                continue;
            }
            $result[$field] = [
                'attribute' => $field,
                'format'    => 'crudLink',
                'visible'   => true,
                'label'     => $model instanceof ActiveRecord ? $model->getRelationLabel($relation, $field) : null,
            ];
        }
        return $result;
    }
}
