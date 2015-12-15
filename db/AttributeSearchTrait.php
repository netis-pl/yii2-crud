<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use netis\crud\web\EnumCollection;
use netis\crud\web\Formatter;
use Yii;
use yii\db\Connection;
use yii\db\Query;

trait AttributeSearchTrait
{
    /**
     * Use a distinct compare value for each column. Primary and foreign keys support multiple values.
     * @param \yii\db\ActiveQuery $query
     * @return \yii\db\ActiveQuery
     */
    protected function addAttributesSearchConditions(\yii\db\ActiveQuery $query)
    {
        $tablePrefix = $this->getDb()->getSchema()->quoteSimpleTableName('t');
        $conditions = ['and'];
        $formats = $this->attributeFormats();
        $attributes = $this->attributes();
        $relations = $this->relations();
        $validAttributes = array_diff($attributes, array_keys($this->getErrors()));
        $attributeValues = $this->getAttributes($validAttributes);
        $formatter = Yii::$app->formatter;
        /** @var EnumCollection $enums */
        $enums = $formatter instanceof Formatter ? $formatter->getEnums() : null;
        foreach ($validAttributes as $attribute) {
            $value = $attributeValues[$attribute];
            if ($value === null || !isset($formats[$attribute])
                || ($enums !== null && !is_array($formats[$attribute]) && $enums->has($formats[$attribute]))
            ) {
                continue;
            }

            if (in_array($attribute, $relations)) {
                // only hasMany relations should be ever marked as valid attributes
                $conditions[] = $this->getRelationCondition($this->getRelation($attribute), $value);
            } else {
                $conditions[] = $this->getAttributeCondition($attribute, $value, $formats, $tablePrefix, $this->getDb());
            }
        }
        // don't clear attributes to allow rendering filled search form
        //$this->setAttributes(array_fill_keys($attributes, null));
        if ($conditions !== ['and']) {
            $query->andWhere($conditions);
        }
        return $query;
    }

    /**
     * @param string $attribute
     * @param string $value
     * @param array $formats
     * @param string $tablePrefix
     * @param Connection $db
     * @return array in format supported by Query::where()
     */
    protected function getAttributeCondition($attribute, $value, $formats, $tablePrefix, $db)
    {
        $likeOp = $db->driverName === 'pgsql' ? 'ILIKE' : 'LIKE';
        $columnName = $tablePrefix . '.' . $db->getSchema()->quoteSimpleColumnName($attribute);
        $value = array_filter(array_map('trim', explode(ActiveSearchInterface::TOKEN_SEPARATOR, $value)));
        switch ($formats[$attribute]) {
            default:
                if (!is_string($value) || strlen($value) < 2 || ($value{0} !== '>' && $value{0} !== '<')) {
                    return [$columnName => $value];
                }

                $op = substr($value, 0, $value{1} !== '=' ? 1 : 2);
                $value = substr($value, strlen($op));
                if (trim($value) === '') {
                    return [];
                }

                return [$op, $columnName, $value];
            case 'string':
            case 'text':
            case 'email':
            case 'url':
                if (is_array($value)) {
                    $result = ['or'];
                    foreach ($value as $token) {
                        $result[] = [$likeOp, $columnName, $token];
                    }

                    return $result;
                }
                return [$likeOp, $columnName, $value];
            case 'json':
                $subquery = (new Query())
                    ->select(1)
                    ->from('json_array_elements(' . $columnName . ') a')
                    ->where([$likeOp, 'a::text', $value]);
                return ['exists', $subquery];
        }
    }

    /**
     * Only hasMany relations should be ever marked as valid attributes.
     * @param \yii\db\ActiveQuery $relation
     * @param array $value
     * @return array an IN condition with a subquery
     */
    protected function getRelationCondition($relation, $value)
    {
        /** @var \yii\db\ActiveRecord $relationClass */
        $relationClass = $relation->modelClass;
        if ($relation->via !== null) {
            /* @var $viaRelation \yii\db\ActiveQuery */
            $viaRelation = is_array($relation->via) ? $relation->via[1] : $relation->via;
            /** @var \yii\db\ActiveRecord $viaClass */
            $viaClass = $viaRelation->modelClass;
            $subquery = (new Query)
                ->select(array_map(function ($key) {
                    return 'j.' . $key;
                }, array_keys($viaRelation->link)))
                ->from(['t' => $relationClass::tableName()])
                ->innerJoin(['j' => $viaClass::tableName()], implode(' AND ', array_map(function ($leftKey, $rightKey) {
                    return 't.' . $leftKey .' = j.' . $rightKey;
                }, array_keys($relation->link), array_values($relation->link))))
                ->where(['IN', array_map(function ($key) {
                    return 't.' . $key;
                }, $relationClass::primaryKey()), $value]);
            $linkKeys = array_values($viaRelation->link);
        } else {
            $subquery = (new Query)
                ->select(array_keys($relation->link))
                ->from(['t' => $relationClass::tableName()])
                ->where(['IN', array_map(function ($key) {
                    return 't.' . $key;
                }, $relationClass::primaryKey()), $value]);
            $linkKeys = array_values($relation->link);
        }
        return ['IN', array_map(function ($key) {
            return 't.' . $key;
        }, $linkKeys), $subquery];
    }
}
