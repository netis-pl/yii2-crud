<?php

/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;
use yii\base\Model;

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
            call_user_func($this->checkAccess, $this->id, $model);
        }
        return [
            'model'      => $model,
            'attributes' => $this->getDetailAttributes($model),
            'relations'  => $this->getModelRelations($model),
        ];
    }

    /**
     * Retrieves detail view attributes configuration using the modelClass.
     * @param Model $model
     * @return array detail view attributes
     */
    public function getDetailAttributes($model)
    {
        if (!$model instanceof ActiveRecord) {
            return $model->attributes();
        }
        /** @var ActiveRecord $model */
        $formats = $model->attributeFormats();
        $keys    = self::getModelKeys($model);
        list($behaviorAttributes, $blameableAttributes) = self::getModelBehaviorAttributes($model);
        $attributes = [];
        foreach ($model->attributes() as $attribute) {
            if (in_array($attribute, $keys) || in_array($attribute, $behaviorAttributes)) {
                continue;
            }
            $attributes[] = $attribute . ':' . $formats[$attribute];
        }
        foreach ($model->relations() as $relation) {
            $activeRelation = $model->getRelation($relation);
            //skip if has many relation
            if ($activeRelation->multiple) {
                continue;
            }
            foreach ($activeRelation->link as $left => $right) {
                if (in_array($left, $blameableAttributes)) {
                    continue;
                }
            }

            if (!Yii::$app->user->can($activeRelation->modelClass . '.read')) {
                continue;
            }
            $attributes[] = [
                'attribute' => $relation,
                'format'    => 'crudLink',
                'visible'   => true,
            ];
        }
        return $attributes;
    }
}
