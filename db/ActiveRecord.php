<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\db;

use yii\base\InvalidParamException;
use yii\db\Schema;
use yii\validators\RequiredValidator;
use yii\web\IdentityInterface;

/**
 * ActiveRecord class extended with the following functionality:
 * - attached the linkable, labels and trackable behaviors by default
 * - ability to cast to string
 * - added relations() method to return list of relations
 * - added attributeFormats() to return default attribute formats
 *
 * @package netis\crud\crud
 * @method bool isRelated(array $relations, IdentityInterface $user = null)
 * @method array getCheckedRelations($userId, $permissionName, array $params = [])
 * @method bool saveRelations(array $data, $formName = null)
 * @method void linkByKeys(\yii\db\ActiveQuery $relation, array $keys, array $removeKeys = null)
 * @method string getCrudLabel($operation = null)
 * @method string getRelationLabel(\yii\db\ActiveQuery $activeRelation, $relation)
 * @method string getLocalLabel($attribute, $language = null)
 * @method null beginChangeset() {@link \nineinchnick\audit\behaviors\TrackableBehavior::beginChangeset()}
 * @method null endChangeset() {@link \nineinchnick\audit\behaviors\TrackableBehavior::endChangeset()}
 * @method ActiveRecord loadVersion($version_id)
 * @method array getAttributeVersions($attribute)
 * @method array getRecordedVersions()
 * @method \netis\crud\db\ActiveQuery getRelation($name, $throwException = true)
 * @method static \netis\crud\db\ActiveQuery find()
 * @method array filteringRules() {@link FilterAttributeValuesTrait::filteringRules()}
 * @method array filteringScenarios() {@link FilterAttributeValuesTrait::filteringScenarios()}
 * @method string[] activeFilterAttributes() {@link FilterAttributeValuesTrait::activeFilterAttributes()}
 * @method void filterAttributes(array $attributeNames = null) {@link FilterAttributeValuesTrait::filterAttributes(array $attributeNames = null)}
 * @method void beforeFilter() {@link FilterAttributeValuesTrait::beforeFilter()}
 * @method void afterFilter() {@link FilterAttributeValuesTrait:afterFilter()}
 * @method \ArrayObject|\yii\validators\Validator[] getFilterValidators() {@link FilterAttributeValuesTrait:getFilterValidators()}
 * @method \yii\validators\Validator[] getActiveFilters() {@link FilterAttributeValuesTrait:getActiveFilters()}
 * @method array createFilters() {@link FilterAttributeValuesTrait:createFilters()}
 */
class ActiveRecord extends \yii\db\ActiveRecord
{
    use FilterAttributeValuesTrait;

    /**
     * The name of the create scenario.
     */
    const SCENARIO_CREATE = 'create';
    /**
     * The name of the update scenario.
     */
    const SCENARIO_UPDATE = 'update';
    /**
     * @event ModelEvent an event raised at the beginning of [[filter()]].
     */
    const EVENT_BEFORE_FILTER = 'beforeFilter';
    /**
     * @event ModelEvent an event raised at the beginning of [[filter()]].
     */
    const EVENT_AFTER_FILTER = 'afterFilter';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'authorizer' => [
                'class' => \netis\rbac\AuthorizerBehavior::className(),
            ],
            'linkable' => [
                'class' => \netis\crud\db\LinkableBehavior::className(),
            ],
            'labels' => [
                'class' => \netis\crud\db\LabelsBehavior::className(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        foreach ([self::SCENARIO_CREATE, self::SCENARIO_UPDATE] as $scenario) {
            if (!isset($scenarios[$scenario])) {
                $scenarios[$scenario] = $scenarios[self::SCENARIO_DEFAULT];
            }
        }
        return $scenarios;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        /** @var \netis\crud\db\LabelsBehavior */
        if (($string = $this->getBehavior('labels')) !== null) {
            return $string->getLabel();
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
     * @inheritdoc
     * Relations are never safe, even if they have validation rules.
     * @see validateRelation()
     */
    public function safeAttributes()
    {
        return array_diff(parent::safeAttributes(), $this->relations());
    }

    /**
     * Validates related models.
     * @param string $attribute the attribute currently being validated
     * @param mixed $params the value of the "params" given in the rule
     */
    public function validateRelation($attribute, $params)
    {
        $relation = $this->getRelation($attribute);
        $attributes = isset($params['attributes']) ? $params['attributes'] : null;
        $errors = [];

        $models = $this->$attribute;
        if (!$relation->multiple) {
            $models = [$models];
        }
        /** @var \yii\db\ActiveRecord[] $models */
        foreach ($models as $index => $model) {
            if (isset($params['scenario'])) {
                $model->scenario = $params['scenario'];
            }

            if ($model->validate($attributes)) {
                continue;
            }
            $errors[] = (string)$model . ': ' . \yii\helpers\Html::errorSummary($model, ['header' => '']) ;
        }

        $this->populateRelation($attribute, $relation->multiple ? $models : $models[0]);
        if (empty($errors)) {
            return;
        }

        $errors = "<ul><li>" . implode("</li>\n<li>", $errors) . "</li></ul>";

        $placeholders = [
            'attribute' => $this->getRelationLabel($relation, $attribute),
        ];
        $message = $relation->multiple
            ? \Yii::t('app', '{attribute} have invalid items:', $placeholders)
            : \Yii::t('app', '{attribute} is invalid:', $placeholders);
        $this->addError($attribute, $message . $errors);
    }

    /**
     * Returns the attribute formats. Possible formats include:
     * - text: string, text, email, url
     * - numbers: boolean, smallint, integer, bigint, float, decimal, money, currency, minorCurrency
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
            Schema::TYPE_FLOAT => 'decimal',
            Schema::TYPE_DOUBLE => 'decimal',
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
                $formats[$attribute] = 'raw';
                continue;
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
     * @inheritdoc
     */
    public function load($data, $formName = null)
    {
        if (!parent::load($data, $formName)) {
            return false;
        }

        $scope = $formName === null ? $this->formName() : $formName;
        $attributes = [];
        if ($scope === '' && !empty($data)) {
            $attributes = array_keys($data);
        } elseif (isset($data[$scope])) {
            $attributes = array_keys($data[$scope]);
        }
        //filter only those attributes that was set (only safe attributes can be set)
        $this->filterAttributes(array_intersect($attributes, $this->safeAttributes()));
        return true;
    }

    /**
     * Adds _label field to serialized array if model has a __toString() method.
     *
     * @inheritdoc
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        $data = parent::toArray($fields, $expand, $recursive);
        
        if (method_exists($this, '__toString')) {
            $data['_label'] = $this->__toString();
        }
        return $data;
    }

    /**
     * Returns a value indicating whether the attribute is required.
     * This is determined by checking if the attribute is associated with a
     * [[\yii\validators\RequiredValidator|required]] validation rule in the
     * current [[scenario]].
     *
     * Note that when the validator has a conditional validation applied using
     * [[\yii\validators\RequiredValidator::$when|$when]] this method will return
     * `false` regardless of the `when` condition because it may be called be
     * before the model is loaded with data.
     *
     * @param string $attribute attribute name
     * @return boolean whether the attribute is required
     */
    public function isAttributeRequired($attribute)
    {
        foreach ($this->getActiveValidators($attribute) as $validator) {
            if ($validator instanceof RequiredValidator && ($validator->when === null || call_user_func($validator->when, $this, $attribute))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Loads default values from database table schema
     *
     * You may call this method to load default values after creating a new instance:
     *
     * ```php
     * // class Customer extends \yii\db\ActiveRecord
     * $customer = new Customer();
     * $customer->loadDefaultValues();
     * ```
     *
     * @param boolean $skipIfSet whether existing value should be preserved.
     * This will only set defaults for attributes that are `null`.
     * @return $this the model instance itself.
     */
    public function loadDefaultValues($skipIfSet = true)
    {
        foreach ($this->getTableSchema()->columns as $column) {
            if ($column->defaultValue !== null && (!$skipIfSet || $this->{$column->name} === null)) {
                $this->{$column->name} = $column->defaultValue;
            }
            if (($column->type === Schema::TYPE_DATETIME || $column->type === Schema::TYPE_TIMESTAMP
                    || $column->type === Schema::TYPE_TIME || $column->type === Schema::TYPE_DATE) && $column->defaultValue == 'now()') {
                $this->{$column->name} = 'now';
            }
        }
        return $this;
    }

    /**
     * Returns related model class to specified foreign key.
     *
     * @param $foreignKey
     *
     * @return null|ActiveRecord
     */
    public function getRelatedModel($foreignKey)
    {
        $relatedTable = null;
        foreach(self::getTableSchema()->foreignKeys as $foreignKeys) {
            if (!isset($foreignKeys[$foreignKey])) {
                continue;
            }

            $relatedTable = $foreignKeys[0];
            break;
        }

        if ($relatedTable === null) {
            return null;
        }

        $relatedModel = null;
        foreach ($this->relations() as $relationName) {
            $relation = $this->getRelation($relationName);
            $modelClass = $relation->modelClass;
            if ($modelClass::getTableSchema()->fullName === $relatedTable) {
                return $relation->modelClass;
            }
        }

        return null;
    }
}
