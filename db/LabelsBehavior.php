<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use netis\utils\crud\ActiveRecord;
use yii\base\Behavior;

/**
 * LabelsBehavior allows to configure how a model is cast to string and its other labels.
 * @package netis\utils\db
 */
class LabelsBehavior extends Behavior
{
    /**
     * @var array Attributes joined to form string representation.
     */
    public $attributes;
    /**
     * @var string Separator used when joining attribute values.
     */
    public $separator = ' ';
    /**
     * @var array labels, required keys: default, index, create, read, update, delete
     */
    public $crudLabels = [];
    /**
     * @var array relation labels
     */
    public $relationLabels = [];
    /**
     * @var callable a callable that returns localized labels
     */
    public $localLabels;
    /**
     * @var string class name of the owner model
     */
    private $modelClass;
    /**
     * @var array cached relation labels
     */
    private $cachedRelationLabels = [];
    /**
     * @var array cached localized labels
     */
    private static $cachedLocalLabels;

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        $this->crudLabels = array_merge([
            'default' => null,
            'relation' => null,
            'index' => null,
            'create' => null,
            'read' => null,
            'update' => null,
            'delete' => null,
        ], $this->crudLabels);

        if ($this->attributes !== null) {
            return;
        }

        /** @var \yii\db\ActiveRecord $model */
        $model = $this->owner;

        // try to resolve attributes if they're not set and owner is an AR model
        if (!($model instanceof \yii\db\ActiveRecord)) {
            return;
        }
        foreach ($model->getTableSchema()->columns as $name => $column) {
            if ($column->type == 'string' || $column->type == 'text') {
                $this->attributes = [$name];
                break;
            }
        }
        if ($this->attributes === null) {
            $this->attributes = $model->primaryKey();
        }
    }

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        /** @var $owner \yii\db\ActiveRecord */
        parent::attach($owner);
        $this->modelClass = $owner::className();
    }

    /**
     * Fetches translated label from the crudLabels property.
     * @param string $operation one of: default, relation, index, create, read, update, delete
     * @return string
     */
    public function getCrudLabel($operation = null)
    {
        return $this->crudLabels[$operation === null ? 'default' : $operation];
    }

    /**
     * Fetches translated label from the relationLabels property or relation model.
     * @param \yii\db\ActiveQuery $activeRelation
     * @param string $relation
     * @return string
     */
    public function getRelationLabel($activeRelation, $relation)
    {
        $modelClass = $activeRelation->modelClass;
        if (isset($this->cachedRelationLabels[$modelClass][$relation])) {
            return $this->cachedRelationLabels[$modelClass][$relation];
        }
        if (isset($this->relationLabels[$relation])) {
            $label = $this->relationLabels[$relation];
        } else {
            /** @var ActiveRecord $relationModel */
            $relationModel = new $modelClass;
            $label = $relationModel->getCrudLabel($activeRelation->multiple ? 'relation' : 'default');
        }
        return $this->cachedRelationLabels[$modelClass][$relation] = $label;
    }

    private function getLocalLabels()
    {
        if (self::$cachedLocalLabels !== null && isset(self::$cachedLocalLabels[$this->modelClass])) {
            return self::$cachedLocalLabels[$this->modelClass];
        }
        return self::$cachedLocalLabels[$this->modelClass] = call_user_func($this->localLabels, $this->owner);
    }

    /**
     * Returnes a localized value of an attribute.
     * @param string $attribute
     * @param string $language if null, defaults to app language
     * @return string
     */
    public function getLocalLabel($attribute, $language = null)
    {
        if ($language === null) {
            $language = \Yii::$app->language;
        }
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        if ($owner->isNewRecord) {
            return $owner->getAttribute($attribute);
        }
        $localLabels = $this->getLocalLabels();

        return !isset($localLabels[$language])
            ? $owner->getAttribute($attribute)
            : $localLabels[$language][$owner->getPrimaryKey()][$attribute];
    }
}
