<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;

use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;
use yii\db\Expression;
use yii\db\Query;
use yii\db\Schema;

class ActiveRecord extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'labels' => [
                'class' => 'netis\utils\db\LabelsBehavior',
            ],
            'trackable' => [
                'class' => 'nineinchnick\audit\behaviors\TrackableBehavior',
                'auditTableName' => 'audits.'.$this->getTableSchema()->name,
            ],
        ];
    }

    public function __toString()
    {
        /** @var \netis\utils\db\LabelsBehavior */
        if (($string = $this->getBehavior('labels')) !== null) {
            return implode($string->separator, $this->getAttributes($string->attributes));
        }
        return implode('/', $this->getPrimaryKey(true));
    }

    /**
     * Returns names of relations available in this model.
     * @return array relation names
     */
    public static function relations()
    {
        return [];
    }

    /**
     * Returns the attribute formats. Possible formats include:
     * - text: string, text, email, url
     * - numbers: boolean, smallint, integer, bigint, float, decimal, money
     * - dates and time: datetime, timestamp, time, date, interval
     * - others: binary.
     *
     * Attribute formats are mainly used for display purpose. For example, given an attribute
     * `price` based on an integer column, we can declare a format `money`, which can be used
     * in grid column or detail attribute definitions.
     *
     * Default formats are detected by analyzing database columns.
     *
     * Note, in order to inherit formats defined in the parent class, a child class needs to
     * merge the parent formats with child formats using functions such as `array_merge()`.
     *
     * Note, when defining enum formats, remember to add an `in` validator to the rules.
     *
     * @return array attribute formats (name => format)
     */
    public function attributeFormats()
    {
        $columns = static::getTableSchema()->columns;
        $attributes = $this->attributes();
        $formatMap = [
            Schema::TYPE_PK => 'integer',
            Schema::TYPE_BIGPK => 'integer',
            Schema::TYPE_STRING => 'text',
            Schema::TYPE_TEXT => 'paragraphs',
            Schema::TYPE_SMALLINT => 'integer',
            Schema::TYPE_INTEGER => 'integer',
            Schema::TYPE_BIGINT => 'integer',
            Schema::TYPE_FLOAT => 'text',
            Schema::TYPE_DOUBLE => 'text',
            Schema::TYPE_DECIMAL => 'decimal',
            Schema::TYPE_DATETIME => 'datetime',
            Schema::TYPE_TIMESTAMP => 'datetime',
            Schema::TYPE_TIME => 'time',
            Schema::TYPE_DATE => 'date',
            Schema::TYPE_BINARY => 'text',
            Schema::TYPE_BOOLEAN => 'boolean',
            Schema::TYPE_MONEY => 'currency',
        ];
        $nameMap = [
            'percent', 'email', 'url',
        ];
        $formats = [];
        foreach ($attributes as $attribute) {
            if (!isset($columns[$attribute])) {
                $formats[$attribute] = Schema::TYPE_STRING;
            }
            $type = $columns[$attribute]->type;
            if ($columns[$attribute]->dbType === 'interval') {
                $formats[$attribute] = 'interval';
                continue;
            }
            foreach ($nameMap as $name) {
                if (!strcasecmp($attribute, $name)) {
                    $formats[$attribute] = $name;
                    continue;
                }
            }
            if (!strcasecmp($attribute, 'price')) {
                if ($columns[$attribute]->type === Schema::TYPE_INTEGER) {
                    $formats[$attribute] = 'minorCurrency';
                } else {
                    $formats[$attribute] = 'currency';
                }
                continue;
            }
            $formats[$attribute] = !isset($formatMap[$type]) ? 'text' : $formatMap[$type];
        }
        return $formats;
    }

    /**
     * Returns the format for the specified attribute.
     * If the attribute looks like `relatedModel.attribute`, then the attribute will be received from the related model.
     * @param string $attribute the attribute name
     * @return string the attribute format
     * @see attributeFormats()
     */
    public function getAttributeFormat($attribute)
    {
        $formats = $this->attributeFormats();
        if (isset($formats[$attribute])) {
            return ($formats[$attribute]);
        }
        if (strpos($attribute, '.') === false) {
            return null;
        }
        $attributeParts = explode('.', $attribute);
        $neededAttribute = array_pop($attributeParts);

        $relatedModel = $this;
        foreach ($attributeParts as $relationName) {
            if ($relatedModel->isRelationPopulated($relationName) && $relatedModel->$relationName instanceof self) {
                $relatedModel = $relatedModel->$relationName;
                continue;
            }
            try {
                $relation = $relatedModel->getRelation($relationName);
            } catch (InvalidParamException $e) {
                return null;
            }
            $relatedModel = new $relation->modelClass;
        }

        $formats = $relatedModel->attributeFormats();
        if (isset($formats[$neededAttribute])) {
            return $formats[$neededAttribute];
        }
        return null;
    }

    /**
     * Establishes the relationship between current model and records matching passed keys.
     * Their foreign key columns are updated to the primary key value of current model.
     *
     * If the relationship involves a junction table, a new row will be inserted into the
     * junction table which contains the primary key values from both models.
     *
     * Note that this method requires that the primary key value is not null.
     *
     * If $removeKeys is null, $keys must be all records to remain associated, including those that do not change.
     *
     * @param string $name the case sensitive name of the relationship
     * @param array $keys primary keys of the records to be linked with the current model
     * @param array $removeKeys primary keys of records to be unlinked with the current model
     * @throws InvalidCallException if the method is unable to link two models.
     */
    public function linkByKeys($name, $keys, $removeKeys = null)
    {
        $relation = $this->getRelation($name);

        if ($relation->via !== null) {
            $this->linkJunctionByKeys($relation, $keys, $removeKeys);
        } else {
            $this->linkDirectByKeys($relation, $keys, $removeKeys);
        }
    }

    /**
     * Reestablishes links between current model and records from $relation specified by $keys.
     * Removes and inserts rows into a junction table.
     * @param ActiveQuery $relation
     * @param array $keys
     * @param array $removeKeys
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\NotSupportedException
     * @throws \yii\db\Exception
     */
    private function linkJunctionByKeys($relation, $keys, $removeKeys = null)
    {
        if ($this->getIsNewRecord()) {
            throw new InvalidCallException('Unable to link model: the model cannot be newly created.');
        }
        /* @var $viaRelation ActiveQuery */
        $viaRelation = is_array($relation->via) ? $relation->via[1] : $relation->via;
        if (is_array($relation->via)) {
            /* @var $viaClass ActiveRecord */
            $viaClass = $viaRelation->modelClass;
            $viaTable = $viaClass::getTableSchema()->fullName;
        } else {
            /* @var $viaTable string */
            $viaTable = reset($relation->via->from);
        }
        $schema = static::getDb()->getSchema();
        if (!empty($keys) || !empty($removeKeys)) {
            static::getDb()->createCommand()
                ->delete(
                    $viaTable,
                    [
                        'and',
                        $removeKeys === null
                            ? $this->buildKeyInCondition('not in', array_values($relation->link), $keys)
                            : $this->buildKeyInCondition('in', array_values($relation->link), $removeKeys),
                        array_combine(
                            array_keys($viaRelation->link),
                            $this->getAttributes(array_values($viaRelation->link))
                        ),
                    ]
                )->execute();
        }

        if (empty($keys)) {
            return;
        }

        /** @var ActiveRecord $relationClass */
        $relationClass = $relation->modelClass;
        $quotedViaTable = $schema->quoteTableName($viaTable);
        $quotedColumns = implode(', ', array_map(
            [$schema, 'quoteColumnName'],
            array_merge(
                array_keys($viaRelation->link),
                array_values($relation->link)
            )
        ));
        $prefixedPrimaryKeys = array_map(function ($c) {
            return 't.'.$c;
        }, array_keys($relation->link));
        $prefixedForeignKeys = array_map(function ($c) {
            return 'j.'.$c;
        }, array_values($relation->link));
        // a subquery is used as a more SQL portable way to specify list of values by putting them in a condition
        $subquery = (new Query())
            ->select(array_merge(
                array_map(
                    function ($c) use ($schema) {
                        return '('.$schema->quoteValue($c).')';
                    },
                    $this->getAttributes(array_values($viaRelation->link))
                ),
                $prefixedPrimaryKeys
            ))
            ->from($relationClass::tableName().' t')
            ->where($this->buildKeyInCondition('in', $prefixedPrimaryKeys, $keys));
        if ($removeKeys === null) {
            $subquery->leftJoin($viaTable.' j', array_combine($prefixedForeignKeys, array_map(function ($k) {
                return new Expression($k);
            }, $prefixedPrimaryKeys)));
            $subquery->andWhere(array_fill_keys($prefixedForeignKeys, null));
        }
        list ($subquery, $params) = static::getDb()->getQueryBuilder()->build($subquery);
        $query = "INSERT INTO $quotedViaTable ($quotedColumns) $subquery";
        static::getDb()->createCommand($query, $params)->execute();
    }

    /**
     * @param ActiveQuery $relation
     * @param array $keys
     * @param array $removeKeys
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    private function linkDirectByKeys($relation, $keys, $removeKeys = null)
    {
        // update related clearing those not in $keys and setting those which are
        //! @todo when TableSchema will allow it, check if 'on update' action is 'set default'
        // when updating the column is not possible this method should not be called at all, an exception should be thrown here
        /** @var ActiveRecord $relatedClass */
        $relatedClass = $relation->modelClass;
        $relatedTable = $relatedClass::getTableSchema()->fullName;
        $leftKeys = array_keys($relation->link);
        $rightKeys = array_values($relation->link);
        if (!empty($keys) || !empty($removeKeys)) {
            static::getDb()->createCommand()
                ->update(
                    $relatedTable,
                    array_fill_keys($leftKeys, null),
                    [
                        'and',
                        $removeKeys === null
                            ? $this->buildKeyInCondition('not in', $relatedClass::getTableSchema()->primaryKey, $keys)
                            : $this->buildKeyInCondition('in', $relatedClass::getTableSchema()->primaryKey, $removeKeys),
                        array_combine($leftKeys, $this->getAttributes($rightKeys)),
                    ]
                )->execute();
        }
        if (empty($keys)) {
            return;
        }
        static::getDb()->createCommand()
            ->update(
                $relatedTable,
                array_combine($leftKeys, $this->getAttributes($rightKeys)),
                [
                    'and',
                    $this->buildKeyInCondition('in', $relatedClass::getTableSchema()->primaryKey, $keys),
                    ['not', array_combine($leftKeys, $this->getAttributes($rightKeys))],
                ]
            )->execute();
    }

    /**
     * Reindexes $keys using $columns return valid 'in' condition like `['in', $columns, $keys]`.
     * @param $op either 'in' or 'not in'
     * @param $columns array of column names
     * @param $keys array of composite values
     * @return array condition like `['in', $columns, $keys]` but with $keys reindexed by $columns
     */
    private function buildKeyInCondition($op, $columns, $keys) {
        return [$op, $columns, array_map(function ($k) use ($columns) {
            return array_combine($columns, $k);
        }, $keys)];
    }

    /**
     * Reads relations information from data sent by end user and uses it to link records to current model.
     * This is an equivalent to load() and save() methods.
     *
     * The data to be loaded is `$data[formName]`, where `formName` refers to the value of [[formName()]].
     * If [[formName()]] is empty, the whole `$data` array will be used.
     * Keys should be relation names. Values are either:
     * - numeric arrays containing primary key values;
     * - associative array with keys: 'add', 'remove'.
     * Warning! Data will NOT be filtered or validated in any way.
     * @param array $data the data array. This is usually `$_POST` or `$_GET`, but can also be any valid array
     * supplied by end user.
     * @param string $formName the form name to be used for loading the data into the model.
     * If not set, [[formName()]] will be used.
     * @return boolean whether no data was sent or the relations has been successfully linked.
     */
    public function saveRelations($data, $formName = null)
    {
        $scope = $formName === null ? $this->formName() : $formName;
        if ($scope !== '') {
            $data = isset($data[$scope]) ? $data[$scope] : [];
        }
        if (empty($data)) {
            return true;
        }
        $relations = array_flip($this->relations());
        foreach ($data as $relationName => $keys) {
            if (!isset($relations[$relationName]) || trim($keys) === '') {
                continue;
            }
            if (!is_array($keys) || isset($keys[0])) {
                $addKeys = Action::importKey($this, Action::explodeEscaped(Action::KEYS_SEPARATOR, $keys));
                $removeKeys = null;
            } elseif (is_array($keys) && isset($keys['add']) && isset($keys['remove'])) {
                $addKeys = Action::importKey($this, Action::explodeEscaped(Action::KEYS_SEPARATOR, $keys['add']));
                $removeKeys = Action::importKey($this, Action::explodeEscaped(Action::KEYS_SEPARATOR, $keys['remove']));
            } else {
                throw new InvalidCallException('Relation keys must be either a string, a numeric array or an array with \'add\' and \'remove\' keys.');
                continue;
            }
            $this->linkByKeys($relationName, $addKeys, $removeKeys);
        }
        return true;
    }
}
