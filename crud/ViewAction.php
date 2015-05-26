<?php

/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use Yii;

class ViewAction extends Action
{

    /**
     * Displays a model.
     * @param string $id the primary key of the model.
     * @return \yii\db\ActiveRecordInterface the model being displayed
     */
    public function run($id)
    {
        $model = $this->findModel($id);
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }
        $attributes = $this->getGridColumns($model);
        return array(
            'model'      => $model,
            'attributes' => $attributes,
        );
    }

    /**
     * Retrieves grid columns configuration using the modelClass.
     * @return array grid columns
     */
    protected function getGridColumns($model)
    {
        if ($this->controller instanceof ActiveController) {
            $formats = $model->attributeFormats();
            $columns = [];
            //array of fkeys and pkeys
            $keys    = $this->getModelKeys($model);
            list($behaviorAttributes, $blameableAttributes) = $this->getModelBehaviorAttributes($model);
            //clear column arrays of pkeys, fkeys and behaviorAttributes
            foreach ($model->attributes() as $attribute) {
                if (in_array($attribute, $keys) || in_array($attribute, $behaviorAttributes)) {
                    continue;
                }
                $columns[] = $attribute . ':' . $formats[$attribute];
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
                $columns[] = [
                    'attribute' => $relation,
                    'format'    => 'crudLink',
                    'visible'   => true,
                ];
            }
            return $columns;
        }
        return $model->attributes();
    }

}
