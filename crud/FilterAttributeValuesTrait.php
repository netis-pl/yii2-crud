<?php

namespace netis\utils\crud;

use ArrayObject;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\validators\Validator;

/**
 * This trait contains all methods needed to perform filtering of attributes after loading data from $_POST. Filtering
 * rules should be defined in {@link filteringRules()} method using the same configuration as in {@link Model::rules()}
 * method.
 *
 * @author Michał Motyczko <michal@motyczko.pl>
 */
trait FilterAttributeValuesTrait
{
    private $_filters;

    /**
     * Returns the validation filtering rules for attributes.*
     *
     * @return array validation rules
     * @see filteringScenarios()
     * @see Model::rules()
     */
    public function filteringRules()
    {
        return [];
    }

    /**
     * Returns a list of filtering scenarios and the corresponding active attributes.
     *
     * @return array a list of scenarios and the corresponding active attributes.
     * @see Model::scenarios()
     */
    public function filteringScenarios()
    {
        $scenarios = [ActiveRecord::SCENARIO_DEFAULT => []];
        foreach ($this->getFilterValidators() as $validator) {
            foreach ($validator->on as $scenario) {
                $scenarios[$scenario] = [];
            }
            foreach ($validator->except as $scenario) {
                $scenarios[$scenario] = [];
            }
        }
        $names = array_keys($scenarios);

        foreach ($this->getFilterValidators() as $validator) {
            if (empty($validator->on) && empty($validator->except)) {
                foreach ($names as $name) {
                    foreach ($validator->attributes as $attribute) {
                        $scenarios[$name][$attribute] = true;
                    }
                }
            } elseif (empty($validator->on)) {
                foreach ($names as $name) {
                    if (!in_array($name, $validator->except, true)) {
                        foreach ($validator->attributes as $attribute) {
                            $scenarios[$name][$attribute] = true;
                        }
                    }
                }
            } else {
                foreach ($validator->on as $name) {
                    foreach ($validator->attributes as $attribute) {
                        $scenarios[$name][$attribute] = true;
                    }
                }
            }
        }

        foreach ($scenarios as $scenario => $attributes) {
            if (!empty($attributes)) {
                $scenarios[$scenario] = array_keys($attributes);
            }
        }

        foreach ([ActiveRecord::SCENARIO_CREATE, ActiveRecord::SCENARIO_UPDATE] as $scenario) {
            if (!isset($scenarios[$scenario])) {
                $scenarios[$scenario] = $scenarios[ActiveRecord::SCENARIO_DEFAULT];
            }
        }
        return $scenarios;
    }

    /**
     * Returns the attribute names that are subject to validation in the current scenario.
     * @return string[] safe attribute names
     */
    public function activeFilterAttributes()
    {
        $scenario = $this->getScenario();
        $scenarios = $this->filteringScenarios();

        $attributes = isset($scenarios[$scenario]) ? $scenarios[$scenario] : $scenarios[ActiveRecord::SCENARIO_DEFAULT];
        foreach ($attributes as $i => $attribute) {
            if ($attribute[0] === '!') {
                $attributes[$i] = substr($attribute, 1);
            }
        }

        return $attributes;
    }

    /**
     * Performs the data filtering.
     *
     * This method executes the filtering rules applicable to the current [[scenario]].
     *
     * @param array $attributeNames list of attribute names that should be filtered.
     * If this parameter is empty, it means any attribute listed in the applicable
     * filtering rules should be filtered.
     */
    public function filterAttributes($attributeNames = null)
    {
        $this->beforeFilter();

        if ($attributeNames === null) {
            $attributeNames = $this->activeFilterAttributes();
        }

        if (empty($attributeNames)) {
            return;
        }

        foreach ($this->getActiveFilters() as $validator) {
            $validator->validateAttributes($this, $attributeNames);
        }
        $this->afterFilter();
    }

    /**
     * This method is invoked before filtering starts.
     * The default implementation raises an {@link ActiveRecord::EVENT_BEFORE_FILTER} event.
     */
    public function beforeFilter()
    {
        $this->trigger(ActiveRecord::EVENT_BEFORE_FILTER);
    }

    /**
     * This method is invoked after filtering ends.
     * The default implementation raises an {@link ActiveRecord::EVENT_AFTER_FILTER} event.
     */
    public function afterFilter()
    {
        $this->trigger(ActiveRecord::EVENT_AFTER_FILTER);
    }

    /**
     * Returns all the filtering validators declared in [[filteringRules()]].
     *
     * @return ArrayObject|\yii\validators\Validator[] all the filtering validators declared in the model.
     * @see Model::getValidators()
     */
    public function getFilterValidators()
    {
        if ($this->_filters === null) {
            $this->_filters = $this->createFilters();
        }
        return $this->_filters;
    }

    /**
     * Returns the filtering validators applicable to the current [[scenario]].
     * @param string $attribute the name of the attribute whose applicable validators should be returned.
     * If this is null, the validators for ALL attributes in the model will be returned.
     * @return \yii\validators\Validator[] the validators applicable to the current [[scenario]].
     */
    public function getActiveFilters($attribute = null)
    {
        $validators = [];
        $scenario = $this->getScenario();
        foreach ($this->getFilterValidators() as $validator) {
            if ($validator->isActive($scenario) && ($attribute === null || in_array($attribute, $validator->attributes, true))) {
                $validators[] = $validator;
            }
        }
        return $validators;
    }

    /**
     * Creates filtering validator objects based on the filtering rules specified in [[filteringRules()]].
     * Unlike [[getFilters()]], each time this method is called, a new list of filtering validators will be returned.
     * @return ArrayObject validators
     * @throws InvalidConfigException if any filtering rule configuration is invalid
     */
    public function createFilters()
    {
        $validators = new ArrayObject;
        foreach ($this->filteringRules() as $rule) {
            if ($rule instanceof Validator) {
                $validators->append($rule);
            } elseif (is_array($rule) && isset($rule[0], $rule[1])) { // attributes, validator type
                $validator = Validator::createValidator($rule[1], $this, (array) $rule[0], array_slice($rule, 2));
                $validators->append($validator);
            } else {
                throw new InvalidConfigException('Invalid validation rule: a rule must specify both attribute names and validator type.');
            }
        }
        return $validators;
    }
}
