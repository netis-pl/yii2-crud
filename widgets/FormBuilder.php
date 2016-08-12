<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\crud\widgets;

use kartik\datetime\DateTimePicker;
use maddoger\widgets\Select2;
use maddoger\widgets\Select2BootstrapAsset;
use netis\crud\assets\RelationsAsset;
use netis\crud\assets\Select2HelperAsset;
use netis\crud\crud\Action;
use netis\crud\db\ActiveQuery;
use netis\crud\db\ActiveRecord;
use netis\crud\web\Formatter;
use omnilight\widgets\DatePicker;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\Object;
use yii\base\Widget;
use yii\bootstrap\Modal;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;
use yii\widgets\ActiveForm;
use netis\crud\widgets\ActiveField;

class FormBuilder extends Object
{
    const MODAL_MODE_NEW_RECORD = 1;
    const MODAL_MODE_EXISTING_RECORD = 2;
    const MODAL_MODE_ADVANCED_SEARCH = 3;

    /**
     * @var null|ActiveForm Form used to create active fields
     */
    public $form = null;

    /**
     * @var ActiveField[] Form fields
     */
    private $fields = [];

    /**
     * @var ActiveRecord Model for which fields will be created
     */
    public $model = null;

    /**
     * @var array Attributes for which fields will be created. Attributes can be config arrays, ActiveField objects or closures.
     */
    public $attributes = [];

    /**
     * @var string[] Attributes that should be hidden
     */
    public $hiddenAttributes = [];

    /**
     * @var string Active field class used for creating fields.
     */
    public $activeFieldClass = ActiveField::class;

    /**
     * @var array Default field options
     */
    public $fieldOptions = [];

    public function init()
    {
        if ($this->form === null) {
            $this->form = new DummyActiveForm();
            $this->form->layout = 'default';
        }
    }

    /**
     * @param string $attribute
     * @return ActiveField
     * @throws InvalidConfigException
     */
    private function getActiveField($attribute)
    {
        $config = [
            'class'     => $this->activeFieldClass,
            'model'     => $this->model,
            'attribute' => $attribute,
            'form'      => $this->form,
        ];

        return Yii::createObject(ArrayHelper::merge($this->fieldOptions, $config));
    }

    /**
     * Configures $field as boolean
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function booleanField($field, $options = [])
    {
        if (!ArrayHelper::remove($options, 'multiple', false)) {
            return $field->checkbox($options);
        }
        /** @var Formatter $formatter */
        $formatter = Yii::$app->formatter;

        return $field->inline()->radioList([
            '0' => $formatter->booleanFormat[0],
            '1' => $formatter->booleanFormat[1],
            ''  => Yii::t('app', 'Any'),
        ], $options);
    }

    /**
     * Configures $field as shortLength
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function shortLengthField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        $field->inputTemplate = '<div class="input-group">{input}<span class="input-group-addon">m</span></div>';
        return $field->textInput($options);
    }

    /**
     * Configures $field as shortWeight
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function shortWeightField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        $field->inputTemplate = '<div class="input-group">{input}<span class="input-group-addon">kg</span></div>';
        return $field->textInput($options);
    }

    /**
     * Configures $field as multiplied
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function multipliedField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        return $field->textInput($options);
    }

    /**
     * Configures $field as integer
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function integerField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        return $field->textInput($options);
    }

    /**
     * Configures $field as time
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function timeField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        return $field->textInput($options);
    }

    /**
     * Configures $field as datetime
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function datetimeField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        $field = $this->dateField($field, $options);
        $field->parts['{input}'] = ArrayHelper::merge($field->parts['{input}'], [
            'class'         => DateTimePicker::className(),
            'convertFormat' => true,
        ]);
        return $field;
    }

    /**
     * Configures $field as date
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function dateField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);

        if (!isset($options['class'])) {
            $options['class'] = 'form-control';
        }

        $field->parts['{input}'] = [
            'class'     => DatePicker::className(),
            'model'     => $this->model,
            'attribute' => $field->attribute,
            'options'   => $options,
        ];

        return $field;
    }

    /**
     * Configures $field as enum
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function enumField($field, $options = [])
    {
        /** @var Formatter $formatter */
        $formatter = Yii::$app->formatter;
        $attributeName = Html::getAttributeName($field->attribute);
        $attributeFormat = $this->model->getAttributeFormat($attributeName);

        $items = $formatter->getEnums()->get($attributeFormat[1]);
        if (!ArrayHelper::remove($options, 'multiple', false)) {
            $column = $this->model->getTableSchema()->getColumn($attributeName);
            if ($column !== null && $column->allowNull) {
                $options['prompt'] = self::getPrompt();
            }
            $field->dropDownList($items, $options);
            return $field;
        }

        $options = ArrayHelper::merge([
            'class'       => 'select2',
            'placeholder' => self::getPrompt(),
            'multiple'    => 'multiple',
        ], $options);

        $field->parts['{input}'] = [
            'class'         => Select2::className(),
            'model'         => $this->model,
            'attribute'     => $field->attribute,
            'items'         => $items,
            'clientOptions' => [
                'allowClear'    => true,
                'closeOnSelect' => true,
            ],
            'options'       => $options,
        ];
        return $field;
    }

    /**
     * Configures $field as flags
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     * @throws InvalidConfigException
     */
    protected function flagsField($field, $options = [])
    {
        throw new InvalidConfigException('Flags format is not supported by ' . get_called_class());
    }

    /**
     * Configures $field as paragraphs
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function paragraphsField($field, $options = [])
    {
        if (ArrayHelper::remove($options, 'multiple', false)) {
            return $field->textInput($options);
        }

        return $field->textarea(array_merge(['cols' => '80', 'rows' => '10'], $options));
    }

    /**
     * Configures $field as file
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function fileField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        return $field->fileInput($options);
    }

    /**
     * Configures $field as text
     *
     * @param \yii\bootstrap\ActiveField $field
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    protected function textField($field, $options = [])
    {
        ArrayHelper::remove($options, 'multiple', false);
        $attributeName = Html::getAttributeName($field->attribute);
        $column = $this->model->getTableSchema()->getColumn($attributeName);
        if ($column && $column->type === 'string' && $column->size !== null) {
            $options['maxlength'] = $column->size;
        }
        return $field->textInput($options);
    }

    /**
     * Creates ActiveField for attribute.
     *
     * @param string $attribute
     * @param array $options
     * @return \yii\bootstrap\ActiveField
     */
    public function field($attribute, $options = [])
    {
        $attributeName = Html::getAttributeName($attribute);
        if ($this->model->optimisticLock() === $attributeName) {
            return $this->getActiveField($attribute)->hiddenInput()->label(false);
        }

        $attributeFormat = $this->model->getAttributeFormat($attributeName);
        $format = is_array($attributeFormat) ? $attributeFormat[0] : $attributeFormat;

        if (!isset($options['value'])) {
            $options['value'] = $this->fieldValue($attributeName);
        }

        if ($format === null || !$this->hasMethod($format . 'Field')) {
            return $this->textField($this->getActiveField($attribute), $options);
        }

        return call_user_func([$this, $format . 'Field'], $this->getActiveField($attribute), $options);
    }

    public function fieldValue($attribute)
    {
        $value = Html::getAttributeValue($this->model, $attribute);
        if ($this->model->hasErrors($attribute)) {
            return $value;
        }

        /** @var Formatter $formatter */
        $formatter = Yii::$app->formatter;

        $attributeName = Html::getAttributeName($attribute);
        $attributeFormat = $this->model->getAttributeFormat($attributeName);
        $format = is_array($attributeFormat) ? $attributeFormat[0] : $attributeFormat;

        if ($format === 'boolean' || is_bool($value)) {
            return 1;
        }

        $value = is_array($value) ? array_filter(array_map('trim', $value)) : trim($value);

        if ($value === [] || $value === '') {
            return $value;
        }

        $skipFormatting = ['paragraphs', 'file'];
        if (in_array($format, $skipFormatting)) {
            return is_array($value) ? array_map([Html::class, 'encode'], $value) : Html::encode($value);
        }

        try {
            return !is_array($value) ? $formatter->format($value, $attributeFormat) :
                array_map(function ($v) use ($formatter, $attributeFormat) {
                    return $formatter->format($v, $attributeFormat);
                }, $value);
        } catch (InvalidParamException $e) {
            return $value;
        }
    }

    /**
     * Creates related field
     *
     * @param string $relation
     * @param array $options
     *
     * @return ActiveField|null
     * @throws InvalidConfigException
     */
    public function relatedField($relation, $options = [])
    {
        $multiple = ArrayHelper::remove($options, 'multiple', false);

        $activeRelation = $this->model->getRelation(Html::getAttributeName($relation));

        if (!$activeRelation->multiple) {
            $hiddenAttributes = array_flip($this->hiddenAttributes);
            foreach ($activeRelation->link as $left => $right) {
                if (!$this->model->isAttributeSafe($right)) {
                    return null;
                }

                if (!isset($hiddenAttributes[$right])) {
                    continue;
                }

                return $this->getActiveField($right)->label(false)->hiddenInput();
            }
        }

        if (!Yii::$app->user->can($activeRelation->modelClass . '.read')) {
            return null;
        }

        if (count($activeRelation->link) > 1) {
            throw new InvalidConfigException('Composite key relations are not supported by ' . get_called_class());
        }

        if (isset($options['class']) && $options['class'] !== Select2::class) {
            $widgetOptions = [];
        } else {
            $widgetOptions = self::getRelationWidgetOptions($this->model, $relation, $activeRelation, $multiple);
        }

        $label = null;
        if ($this->model instanceof ActiveRecord) {
            $label = $this->model->getRelationLabel($activeRelation, Html::getAttributeName($relation));
        }

        $isMany = $activeRelation->multiple;
        $foreignKeys = array_values($activeRelation->link);
        $foreignKey = reset($foreignKeys);

        $field = $this->getActiveField($isMany ? $relation : $foreignKey);
        $field->parts['{input}'] = ArrayHelper::merge($widgetOptions, $options);
        return $field->label($label);
    }

    /**
     * Returns form fields.
     *
     * @return \yii\widgets\ActiveField[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function createField($attribute, $options = [])
    {
        $keys = Action::getModelKeys($this->model);
        $hiddenAttributes = array_flip($this->hiddenAttributes);

        list($behaviorAttributes, $blameableAttributes) = Action::getModelBehaviorAttributes($this->model);
        $relations = $this->model->relations();

        $attributeName = Html::getAttributeName($attribute);
        if (in_array($attributeName, $relations)) {
            return $this->relatedField($attribute, $options);
        }

        if (!$this->model->isAttributeSafe($attributeName) || in_array($attributeName, $keys)
            || (in_array($attributeName, $behaviorAttributes))
        ) {
            return null;
        }

        if (isset($hiddenAttributes[$attributeName])) {
            return Html::activeHiddenInput($this->model, $attribute, $options);
        }

        return $this->field($attribute, $options);
    }

    /**
     * Builds fields from attributes configuration array.
     *
     * @param bool $multiple
     *
     * @return FormBuilder
     * @throws InvalidConfigException
     */
    public function createFields($multiple = false)
    {
        if (!$this->model instanceof \yii\db\ActiveRecord) {
            $this->fields = $this->model->safeAttributes();
            return $this;
        }

        if (($versionAttribute = $this->model->optimisticLock()) !== null) {
            $hiddenAttributes[$versionAttribute] = true;
        }

        $this->fields = [];
        foreach ($this->attributes as $key => $field) {
            //we should skip relation attributes which could be defined in search model in format relation.attribute
            if (is_string($field) && strpos($field, '.') !== false) {
                continue;
            }

            //if field is string then we assume it's attribute name
            if (is_string($field)) {
                $key = $field;
                $field = $this->createField($field, ['multiple' => $multiple]);
            }

            if ($field === null) {
                continue;
            }

            if (is_callable($field)) {
                $field = call_user_func($field, $this->model);
            }

            if ($field instanceof \yii\widgets\ActiveField || is_string($field)) {
                $this->fields[$key] = $field;
                continue;
            }

            throw new InvalidConfigException('Field definition must be either an attribute name, ActiveField or a callable.');
        }

        return $this;
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param \yii\db\ActiveQuery $activeRelation
     * @return string
     * @throws Exception
     */
    protected static function getRelationValue($model, $relation, $activeRelation)
    {
        $foreignKeys = array_values($activeRelation->link);

        $relation = Html::getAttributeName($relation);
        if ($activeRelation->multiple) {
            if (property_exists($model, $relation)) {
                // special case for search models, where there is a relation property defined that holds the keys
                $value = $model->$relation;
            } else {
                /** @var \yii\db\ActiveRecord $modelClass */
                $modelClass = $activeRelation->modelClass;
                $value = array_map(
                    '\netis\crud\crud\Action::exportKey',
                    $activeRelation->select($modelClass::primaryKey())->asArray()->all()
                );
            }
            if (is_array($value)) {
                $value = Action::implodeEscaped(Action::KEYS_SEPARATOR, $value);
            }
            return $value;
        }
        // special case for search models, where fks holds array of keys
        $foreignKey = reset($foreignKeys);
        if (!is_array($model->getAttribute($foreignKey))) {
            return Action::exportKey($model->getAttributes($foreignKeys));
        }
        if (count($foreignKeys) > 1) {
            throw new Exception('Composite foreign keys are not supported for searching.');
        }
        return Action::implodeEscaped(Action::KEYS_SEPARATOR, $model->getAttribute($foreignKey));
    }

    /**
     * @param string $searchRoute
     * @param string $createRoute
     * @param string $jsPrimaryKey
     * @param string $label
     * @param string $relation
     * @return array holds ajaxResults JS callback and clientEvents array
     */
    protected static function getRelationAjaxOptions($searchRoute, $createRoute, $jsPrimaryKey, $label, $relation)
    {
        $searchLabel = Yii::t('app', 'Advanced search');
        $createLabel = Yii::t('app', 'Create new');
        $searchUrl = $searchRoute === null ? null : Url::toRoute($searchRoute);
        $createUrl = $createRoute === null ? null : Url::toRoute($createRoute);
        $createKey = 'create_item';
        $searchKey = 'search_item';
        $script = <<<JavaScript
function (data, page) {
    if (page !== 1) {
        //append search and create items on first page only
        return s2helper.results(data, page);
    }

    var keys = $jsPrimaryKey, values = {};
    if ('$searchUrl') {
        for (var i = 0; i < keys.length; i++) {
            values[keys[i]] = '$searchKey';
        }
        values._label = '-- $searchLabel --';
        data.items.unshift(values);
    }
    if ('$createUrl') {
        values = [];
        for (var i = 0; i < keys.length; i++) {
            values[keys[i]] = '$createKey';
        }
        values._label = '-- $createLabel --';
        data.items.unshift(values);
    }
    return s2helper.results(data, page);
}
JavaScript;
        $ajaxResults = new JsExpression($script);
        $script = <<<JavaScript
function (event) {
    var isSearch = true, isCreate = true;
    if (event.val != '$searchKey') {
        isSearch = false;
    }

    if (event.val != '$createKey') {
        isCreate = false;
    }

    if (!isSearch && !isCreate) {
        return true;
    }
    
    var searchUrl = $(event.target).data('search-url') || '$searchUrl';
    var createUrl = $(event.target).data('create-url') || '$createUrl';

    $(event.target).select2('close');
    $('#relationModal').data('mode', 3);
    $('#relationModal').data('target', $(event.target).attr('id'));
    $('#relationModal').data('title', '$label');
    $('#relationModal').data('relation', '$relation');
    $('#relationModal').data('pjax-url', isSearch ? searchUrl : createUrl);
    $('#relationModal').modal('show');
    event.preventDefault();
    return false;
}
JavaScript;
        $clientEvents = [
            'select2-selecting' => new JsExpression($script),
        ];

        return [$ajaxResults, $clientEvents];
    }

    /**
     * Registers JS code to help initialize Select2 widgets
     * with access to netis\crud\crud\ActiveController API.
     * @param View $view
     */
    public static function registerSelect($view)
    {
        Select2HelperAsset::register($view);
    }

    /**
     * Registers JS code for handling relations.
     * @param View $view
     * @return string modal widget to be embedded in a view
     */
    public static function registerRelations($view)
    {
        RelationsAsset::register($view);
        $options = Json::htmlEncode([
            'i18n'                  => [
                'loadingText' => Yii::t('app', 'Loading, please wait.'),
            ],
            'keysSeparator'         => Action::KEYS_SEPARATOR,
            'compositeKeySeparator' => Action::COMPOSITE_KEY_SEPARATOR,
        ]);
        $view->registerJs("netis.init($options)", View::POS_READY, 'netis.init');

        // init relation tools used in _relations subview
        // relations modal may contain a form and must be rendered outside ActiveForm
        return Modal::widget([
            'id'     => 'relationModal',
            'size'   => Modal::SIZE_LARGE,
            'header' => '<span class="modal-title"></span>',
            'footer' => implode('', [
                Html::button(Yii::t('app', 'Save'), [
                    'id'    => 'relationSave',
                    'class' => 'btn btn-primary',
                ]),
                Html::button(Yii::t('app', 'Cancel'), [
                    'class'        => 'btn btn-default',
                    'data-dismiss' => 'modal',
                    'aria-hidden'  => 'true',
                ]),
            ]),
        ]);
    }

    /**
     * @param ActiveQuery $relation
     * @return array
     */
    public function getRoutes($relation)
    {
        /** @var ActiveRecord $relatedModelClass */
        $relatedModelClass = $relation->modelClass;

        if (($route = Yii::$app->crudModelsMap[$relation->modelClass]) === null) {
            return [null, null, null];
        }

        $allowCreate = Yii::$app->user->can($relation->modelClass . '.create');
        if ($allowCreate && $this->model->isNewRecord && $relation->multiple) {
            foreach ($relation->link as $left => $right) {
                if (!$relatedModelClass::getTableSchema()->getColumn($left)->allowNull) {
                    $allowCreate = false;
                    break;
                }
            }
        }

        if (!$allowCreate) {
            $createRoute = null;
        } else {
            $createRoute = [$route . '/update'];
            if ($relation->multiple) {
                $createRoute['hide'] = implode(',', array_keys($relation->link));
                $scope = (new $relatedModelClass)->formName();
                $primaryKey = $this->model->getPrimaryKey(true);
                foreach ($relation->link as $left => $right) {
                    if (!isset($primaryKey[$right])) {
                        continue;
                    }
                    $createRoute[$scope][$left] = $primaryKey[$right];
                }
            }
        }

        $parts = explode('\\', $relatedModelClass);
        $relatedModelClass = array_pop($parts);
        $relatedSearchModelClass = implode('\\', $parts) . '\\search\\' . $relatedModelClass;
        $searchRoute = !class_exists($relatedSearchModelClass) ? null : [
            $route . '/relation',
            'per-page' => 10,
            'relation' => $relation->inverseOf,
            'id'       => Action::exportKey($this->model->getPrimaryKey()),
            'multiple' => $relation->multiple ? 'true' : 'false',
        ];

        $indexRoute = [$route . '/index'];

        return [$createRoute, $searchRoute, $indexRoute];
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param \yii\db\ActiveRecord $relatedModel
     * @param \yii\db\ActiveQuery $relation
     * @return array array with three arrays: create, search and index routes
     */
    public static function getRelationRoutes($model, $relatedModel, $relation)
    {
        return (new FormBuilder(['model' => $model]))->getRoutes($relation);
    }

    /**
     * Get drop down list items using provided Query.
     *
     * __WARNING__: This method does not append authorized conditions to query and you need append those if needed.
     *
     * @param \yii\db\ActiveQuery $query
     *
     * @return array
     */
    public static function getDropDownItems($query)
    {
        if ($query instanceof ActiveQuery) {
            $query->defaultOrder();
        }

        /** @var \yii\db\ActiveRecord|\netis\rbac\AuthorizerBehavior $model */
        $model = new $query->modelClass;

        $fields = $model::primaryKey();
        if (($labelAttributes = $model->getBehavior('labels')->attributes) !== null) {
            $fields = array_merge($model::primaryKey(), $labelAttributes);
        }

        $flippedPrimaryKey = array_flip($model::primaryKey());
        return ArrayHelper::map(
            $query->from($model::tableName() . ' t')->all(),
            function ($item) use ($fields, $flippedPrimaryKey) {
                /** @var ActiveRecord $item */
                return Action::exportKey(array_intersect_key($item->toArray($fields, []), $flippedPrimaryKey));
            },
            function ($item) use ($fields) {
                /** @var ActiveRecord $item */
                $data = $item->toArray($fields, []);
                return $data['_label'];
            }
        );
    }

    /**
     * Returns {@link \maddoger\widgets\Select2} widget options without ajax configuration.     *
     *
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param \yii\db\ActiveQuery $activeRelation
     * @param bool|false $multiple
     * @param null|array $items
     *
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function getRelationWidgetStaticOptions(
        $model,
        $relation,
        $activeRelation,
        $multiple = false,
        $items = null
    ) {
        $isMany = $activeRelation->multiple;
        $foreignKey = array_values($activeRelation->link)[0];
        /** @var ActiveRecord $relModel */
        $relModel = new $activeRelation->modelClass;

        if ($items === null) {
            $checkedRelations = $relModel->getCheckedRelations(Yii::$app->user->id,
                $activeRelation->modelClass . '.read');
            $query = $relModel::find()->authorized($relModel, $checkedRelations, Yii::$app->user->getIdentity());
            $items = self::getDropDownItems($query);
        }

        //clone model so we could set value for attribute so we would have initialized value for static select2
        $model = clone $model;
        $attribute = $isMany ? $relation : $foreignKey;
        if (!$isMany) {
            $model->$attribute = count($items) <= 1 ? key($items)
                : self::getRelationValue($model, $relation, $activeRelation);
        }
        $dbColumn = $model->getTableSchema()->getColumn($foreignKey);
        $allowClear = $multiple || $isMany ? true : !$model->isAttributeRequired($foreignKey)
            && ($dbColumn === null || $dbColumn->allowNull);

        if (!$allowClear && empty($items)) {
            throw new InvalidConfigException("$foreignKey attribute in {$model::className()} is required but there are no available items");
        }

        if (!$allowClear && empty($items)) {
            Yii::warning("There are no items in control for $foreignKey attribute in {$model::className()}");
        }

        //we get prefix from $relation because it could be in format [3]relation and we need to have [3]foreign_key here
        $relationName = Html::getAttributeName($relation);
        $prefixedFk = str_replace($relationName, $foreignKey, $relation);
        $value = Html::getAttributeValue($model, $isMany ? $relation : $prefixedFk);

        Select2HelperAsset::register(Yii::$app->view);
        return [
            'class'         => Select2::className(),
            'model'         => $model,
            'attribute'     => $isMany ? $relation : $prefixedFk,
            'items'         => $items,
            'clientOptions' => [
                'width'         => '100%',
                'allowClear'    => $allowClear,
                'closeOnSelect' => true,
            ],
            'options'       => array_merge([
                'class'       => 'select2',
                'prompt'      => '',
                'placeholder' => self::getPrompt(),
                'value'       => Action::implodeEscaped(Action::KEYS_SEPARATOR, (array)$value),
            ], $multiple ? ['multiple' => 'multiple'] : []),
        ];
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param \yii\db\ActiveQuery $activeRelation
     * @param bool|false $multiple
     *
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function getRelationWidgetOptions($model, $relation, $activeRelation, $multiple = false)
    {
        /** @var ActiveRecord $relModel */
        $relModel = new $activeRelation->modelClass;
        $formBuilder = new FormBuilder(['model' => $model]);
        list($createRoute, $searchRoute, $indexRoute) = $formBuilder->getRoutes($activeRelation);

        if ($indexRoute === null) {
            return self::getRelationWidgetStaticOptions($model, $relation, $activeRelation, $multiple);
        }

        $isMany = $activeRelation->multiple;
        $foreignKeys = array_values($activeRelation->link);
        $foreignKey = reset($foreignKeys);
        $dbColumns = $model->getTableSchema()->columns;
        $primaryKey = $relModel::primaryKey();

        if (($labelAttributes = $relModel->getBehavior('labels')->attributes) !== null) {
            $fields = array_merge($primaryKey, $labelAttributes);
        } else {
            $fields = $primaryKey;
        }

        $value = self::getRelationValue($model, $relation, $activeRelation);
        $allowClear = $multiple || $isMany ? true : !$model->isAttributeRequired($foreignKey)
            && (!isset($dbColumns[$foreignKey]) || $dbColumns[$foreignKey]->allowNull);

        $jsPrimaryKey = json_encode($primaryKey);
        $jsSeparator = Action::COMPOSITE_KEY_SEPARATOR;
        $jsId = <<<JavaScript
function(object){
    var keys = $jsPrimaryKey, values = [];
    for (var i = 0; i < keys.length; i++) {
         values.push(object[keys[i]]);
    }
    return netis.implodeEscaped('$jsSeparator', values);
}
JavaScript;

        // check if only one option is available and if yes - set it as selected value
        if (!$allowClear && trim($value) === '') {
            $checkedRelations = $relModel->getCheckedRelations(Yii::$app->user->id,
                $activeRelation->modelClass . '.read');
            $relQuery = $relModel::find()
                ->select($primaryKey)
                ->from($relModel::tableName() . ' t')
                ->authorized($relModel, $checkedRelations, Yii::$app->user->getIdentity())
                ->asArray();
            if ($relQuery->count() === 1) {
                $value = $relQuery->one();
                $value = Action::implodeEscaped(Action::KEYS_SEPARATOR, $value);
            }
        }

        $label = null;
        if ($model instanceof ActiveRecord) {
            $label = $model->getRelationLabel($activeRelation, Html::getAttributeName($relation));
        }
        $ajaxResults = new JsExpression('s2helper.results');
        $clientEvents = null;
        if ($indexRoute !== null && ($searchRoute !== null || $createRoute !== null)) {
            list ($ajaxResults, $clientEvents) = self::getRelationAjaxOptions(
                $searchRoute,
                $createRoute,
                $jsPrimaryKey,
                $label,
                $relation
            );
        }

        //we get prefix from $relation because it could be in format [3]relation and we need to have [3]foreign_key here
        $relationName = Html::getAttributeName($relation);
        $prefixedFk = str_replace($relation, $foreignKey, $relationName);
        Select2HelperAsset::register(Yii::$app->view);
        return [
            'class'         => Select2::class,
            'model'         => $model,
            'attribute'     => $isMany ? $relation : $prefixedFk,
            'clientOptions' => array_merge(
                [
                    'formatResult'    => new JsExpression('s2helper.formatResult'),
                    'formatSelection' => new JsExpression('s2helper.formatSelection'),
                    'id'              => new JsExpression($jsId),
                    'width'           => '100%',
                    'allowClear'      => $allowClear,
                    'closeOnSelect'   => true,
                    'initSelection'   => new JsExpression($multiple ? 's2helper.initMulti' : 's2helper.initSingle'),
                    'ajax'            => [
                        'url'         => Url::toRoute(array_merge($indexRoute, [
                            '_format' => 'json',
                            'fields'  => implode(',', $fields),
                        ])),
                        'dataFormat'  => 'json',
                        'quietMillis' => 300,
                        'data'        => new JsExpression('s2helper.data'),
                        'results'     => $ajaxResults,
                    ],
                ],
                $multiple ? ['multiple' => true] : []
            ),
            'clientEvents'  => $clientEvents,
            'options'       => [
                'class'            => 'select2',
                'value'            => $value,
                'placeholder'      => self::getPrompt(),
                //for now handle relations with single column primary keys
                'data-relation-pk' => count($primaryKey) === 1 ? reset($primaryKey) : null,
            ],
        ];
    }

    /**
     * @param \yii\db\ActiveRecord $model
     * @param string $relation
     * @param \yii\db\ActiveQuery $activeRelation
     * @param array $widgetOptions obtained from getRelationWidgetOptions()
     * @return ActiveField
     */
    public static function getRelationWidget($model, $relation, $activeRelation, $widgetOptions)
    {
        $label = null;
        if ($model instanceof ActiveRecord) {
            $label = $model->getRelationLabel($activeRelation, Html::getAttributeName($relation));
        }

        $isMany = $activeRelation->multiple;
        $foreignKeys = array_values($activeRelation->link);
        $foreignKey = reset($foreignKeys);

        $dummyActiveForm = new DummyActiveForm();
        $dummyActiveForm->layout = 'default';

        /** @var ActiveField $field */
        $field = Yii::createObject([
            'class'     => ActiveField::class,
            'model'     => $model,
            'form'      => $dummyActiveForm,
            'attribute' => $isMany ? $relation : $foreignKey,
            'parts'     => [
                '{input}' => $widgetOptions,
            ],
        ]);
        return $field->label($label);
    }

    /**
     * @param \yii\base\Model $model
     * @param array[] $fields
     *
     * @return bool
     */
    public static function hasRequiredFields($model, $fields)
    {
        foreach ($fields as $column) {
            if (!is_array($column)) {
                if ($column instanceof \yii\widgets\ActiveField && $model->isAttributeRequired($column->attribute)) {
                    return true;
                }
                continue;
            }

            foreach ($column as $row) {
                if (!is_array($row)) {
                    if ($row instanceof \yii\widgets\ActiveField && $model->isAttributeRequired($row->attribute)) {
                        return true;
                    }
                    continue;
                }

                if (static::hasRequiredFields($model, $row)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns propmt text for dropdown inputs
     *
     * @return mixed
     * @throws InvalidConfigException
     */
    public static function getPrompt()
    {
        $prompt = null;
        $formatter = Yii::$app->formatter;
        if ($formatter instanceof Formatter) {
            $prompt = $formatter->dropDownPrompt;
        } else {
            $prompt = strip_tags($formatter->nullDisplay);
        }

        if (trim($prompt) === '') {
            throw new InvalidConfigException('Prompt value cannot be empty string!');
        }

        return trim($prompt);
    }

    /*** Static methods to keep BC ***/

    /**
     * Creates ActiveField for attribute.
     *
     * @param ActiveRecord $model
     * @param string $attribute
     * @param array $options
     * @param bool $multiple
     *
     * @return \yii\bootstrap\ActiveField
     * @throws InvalidConfigException
     * @deprecated Use {@link createField} instead
     */
    public static function createActiveField($model, $attribute, $options = [], $multiple = false)
    {
        $options['multiple'] = $multiple;
        return (new static(['model' => $model]))->field($attribute, $options);
    }

    /**
     * Retrieves form fields configuration. Fields can be config arrays, ActiveField objects or closures.
     *
     * @param \yii\base\Model|ActiveRecord $model
     * @param array $fields
     * @param bool $multiple true for multiple values inputs, usually used for search forms
     * @param array $hiddenAttributes list of attribute names to render as hidden fields
     *
     * @return array form fields
     * @throws InvalidConfigException
     * @deprecated Use {@link createFields} and {@link getFields} instead
     */
    public static function getFormFields($model, $fields, $multiple = false, $hiddenAttributes = [])
    {
        return (new static(['model' => $model, 'attributes' => $fields, 'hiddenAttributes' => $hiddenAttributes]))
            ->createFields($multiple)
            ->getFields();
    }

    /**
     * @param \yii\widgets\ActiveForm $form
     * @param string|\yii\widgets\ActiveField $field
     * @return string
     * @deprecated Should not be used at all. Use {@link ActiveField} to handle '{input}' as array.
     */
    public static function renderField($form, $field)
    {
        if (!$field instanceof \yii\widgets\ActiveField) {
            return (string)$field;
        }

        $field->form = $form;

        if (isset($field->parts['{input}']) && is_array($field->parts['{input}'])) {
            /** @var Widget $class */
            $class                   = $field->parts['{input}']['class'];
            $field->parts['{input}'] = $class::widget($field->parts['{input}']);
        }
        return (string)$field;
    }

    /**
     * @param \yii\widgets\ActiveForm $form
     * @param array $fields
     * @param int $topColumnWidth
     * @return string
     * @deprecated Should not be used at all.
     */
    public static function renderRow($form, $fields, $topColumnWidth = 12)
    {
        if (empty($fields)) {
            return '';
        }
        $result = [];
        $oneColumn = false; // optionally: count($fields) == 1;
        $result[] = $oneColumn ? '' : '<div class="row">';
        $columnWidth = ceil($topColumnWidth / count($fields));
        foreach ($fields as $column) {
            $result[] = $oneColumn ? '' : '<div class="col-sm-' . $columnWidth . '">';
            if (!is_array($column)) {
                $result[] = static::renderField($form, $column);
            } else {
                foreach ($column as $row) {
                    if (!is_array($row)) {
                        $result[] = static::renderField($form, $row);
                    } else {
                        $result[] = static::renderRow($form, $row);
                    }
                }
            }
            $result[] = $oneColumn ? '' : '</div>';
        }
        $result[] = $oneColumn ? '' : '</div>';

        return implode('', $result);
    }
}
