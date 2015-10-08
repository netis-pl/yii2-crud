<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\crud;


use yii\base\InvalidParamException;
use yii\db\ActiveQuery;
use yii\db\Schema;
use yii\web\IdentityInterface;

/**
 * ActiveRecord class extended with the following functionality:
 * - attached the linkable, labels and trackable behaviors by default
 * - ability to cast to string
 * - added relations() method to return list of relations
 * - added attributeFormats() to return default attribute formats
 *
 * @package netis\utils\crud
 * @method bool isRelated(array $relations, IdentityInterface $user = null)
 * @method array getCheckedRelations()
 * @method bool saveRelations(array $data, string $formName = null)
 * @method void linkByKeys(\yii\db\ActiveQuery $relation, array $keys, array $removeKeys)
 * @method string getCrudLabel(string $operation = null)
 * @method string getRelationLabel(\yii\db\ActiveQuery $activeRelation, string $relation)
 * @method string getLocalLabel($attribute, $language = null)
 * @method null beginChangeset() {@link \nineinchnick\audit\behaviors\TrackableBehavior::beginChangeset()}
 * @method null endChangeset() {@link \nineinchnick\audit\behaviors\TrackableBehavior::endChangeset()}
 * @method ActiveRecord loadVersion(integer $version_id)
 * @method array getAttributeVersions(string $attribute)
 * @method array getRecordedVersions()
 * @method \netis\utils\db\ActiveQuery getRelation($name, $throwException = true)
 * @method static \netis\utils\db\ActiveQuery find()
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
                'class' => 'netis\rbac\AuthorizerBehavior',
            ],
            'linkable' => [
                'class' => 'netis\utils\db\LinkableBehavior',
            ],
            'labels' => [
                'class' => 'netis\utils\db\LabelsBehavior',
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

    public function __toString()
    {
        /** @var \netis\utils\db\LabelsBehavior */
        if (($string = $this->getBehavior('labels')) !== null) {
            $attributes = $this->getAttributes($string->attributes);
            return implode($string->separator, $attributes);
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
        $valid = true;
        $models = $this->$attribute;
        if (!$relation->multiple) {
            $models = [$models];
        }
        foreach ($models as $model) {
            if (isset($params['scenario'])) {
                $model->scenario = $params['scenario'];
            }
            $valid = $model->validate($attributes) && $valid;
        }
        $this->populateRelation($attribute, $relation->multiple ? $models : $models[0]);
        if (!$valid) {
            $placeholders = ['attribute' => $this->getRelationLabel($relation, $attribute)];
            $message = $relation->multiple
                ? \Yii::t('app', '{attribute} have invalid items.', $placeholders)
                : \Yii::t('app', '{attribute} is invalid.', $placeholders);
            $this->addError($attribute, $message);
        }
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

        $this->filterAttributes();
        return true;
    }

    /**
     * Adds _label field to serialized array if netis\utils\db\LabelsBehavior is configured
     *
     * @inheritdoc
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        $data = parent::toArray($fields, $expand, $recursive);

        /** @var \netis\utils\db\LabelsBehavior $labelsBehavior */
        if (($labelsBehavior = $this->getBehavior('labels')) === null) {
            return $data;
        }

        $attributes = $this->getAttributes($labelsBehavior->attributes);

        $data['_label'] = implode($labelsBehavior->separator, $attributes);

        return $data;
    }
}
