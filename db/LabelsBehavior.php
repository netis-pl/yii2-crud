<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\db;

use yii\base\Behavior;

/**
 * LabelsBehavior allows to configure how a model is cast to string and its other labels.
 * @package netis\utils\crud
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

    public function init()
    {
        $this->crudLabels = array_merge([
            'default' => null,
            'index' => null,
            'create' => null,
            'read' => null,
            'update' => null,
            'delete' => null,
        ], $this->crudLabels);

        if ($this->attributes !== null) {
            return;
        }
        // try to resolve attributes if they're not set and owner is an AR model
        if (!($this->owner instanceof \yii\db\ActiveRecord)) {
            return;
        }
        /** @var \yii\db\ActiveRecord $model */
        $model = $this->owner;
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

    public function getCrudLabel($operation = null)
    {
        return $this->crudLabels[$operation === null ? 'default' : $operation];
    }
}
