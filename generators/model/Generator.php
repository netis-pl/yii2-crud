<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\generators\model;

use Yii;
use yii\base\NotSupportedException;
use yii\db\Schema;
use yii\gii\CodeFile;
use yii\helpers\Inflector;

class Generator extends \yii\gii\generators\model\Generator
{
    public $singularModelClass = true;
    public $searchNs = 'app\models\search';
    public $searchModelClass = '';

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Netis Model Generator';
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return <<<DESC
This generator generates an ActiveRecord class for the specified database table.
Changes:

* generate singular model class names from plural table names
DESC;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['searchModelClass', 'searchNs'], 'trim'],
            [['searchNs'], 'filter', 'filter' => function ($value) {
                return trim($value, '\\');
            }],
            [['searchNs'], 'required'],
            [
                ['searchModelClass'],
                'compare',
                'compareAttribute' => 'modelClass',
                'operator' => '!==',
                'message' => 'Search Model Class must not be equal to Model Class.'
            ],
            [
                ['searchModelClass'],
                'match',
                'pattern' => '/^[\w\\\\]*$/',
                'message' => 'Only word characters and backslashes are allowed.'
            ],
            [
                ['searchNs'],
                'match',
                'pattern' => '/^[\w\\\\]+$/',
                'message' => 'Only word characters and backslashes are allowed.',
            ],
            [['searchModelClass'], 'validateNewClass'],
            [['searchNs'], 'validateNamespace'],
            [['singularModelClass'], 'boolean'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'singularModelClass' => 'Singular Model Class',
            'searchModelClass' => 'Search Model Class',
            'searchNs' => 'ActiveSearch Namespace',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function hints()
    {
        return array_merge(parent::hints(), [
            'singularModelClass' => 'If checked, will generate singular model class from a plural table name.',
            'searchModelClass' => 'This is the name of the search model class to be generated.',
            'searchNs' => 'This is the namespace of the ActiveSearch class
                to be generated, e.g., <code>app\models</code>',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function stickyAttributes()
    {
        return array_merge(parent::stickyAttributes(), ['singularModelClass', 'searchNs']);
    }

    /**
     * Changes:
     * * added search model
     * @inheritdoc
     */
    public function generate()
    {
        $files = [];
        $relations = $this->generateRelations();
        $db = $this->getDbConnection();
        foreach ($this->getTableNames() as $tableName) {
            // model :
            $modelClassName = $this->generateClassName($tableName);
            $queryClassName = ($this->generateQuery) ? $this->generateQueryClassName($modelClassName) : false;
            $searchClassName = ($this->generateQuery) ? $this->generateSearchClassName($modelClassName) : false;
            $tableSchema = $db->getTableSchema($tableName);
            $params = [
                'tableName' => $tableName,
                'className' => $modelClassName,
                'queryClassName' => $queryClassName,
                'tableSchema' => $tableSchema,
                'labels' => $this->generateLabels($tableSchema),
                'rules' => $this->generateRules($tableSchema),
                'relations' => isset($relations[$tableName]) ? $relations[$tableName] : [],
                'behaviors' => $this->generateBehaviors($tableSchema),
            ];
            $files[] = new CodeFile(
                Yii::getAlias('@' . str_replace('\\', '/', $this->ns)) . '/' . $modelClassName . '.php',
                $this->render('model.php', $params)
            );

            if ($queryClassName) {
                $params = [
                    'className' => $queryClassName,
                    'modelClassName' => $modelClassName,
                ];
                $files[] = new CodeFile(
                    Yii::getAlias('@' . str_replace('\\', '/', $this->queryNs)) . '/' . $queryClassName . '.php',
                    $this->render('query.php', $params)
                );
            }

            if ($searchClassName) {
                $params = [
                    'className' => $searchClassName,
                    'modelClassName' => $modelClassName,
                    'queryClassName' => $queryClassName ? $queryClassName : 'ActiveQuery',
                    'labels' => $this->generateLabels($tableSchema),
                    'rules' => $this->generateRules($tableSchema, true),
                ];
                $files[] = new CodeFile(
                    Yii::getAlias('@' . str_replace('\\', '/', $this->searchNs)) . '/' . $searchClassName . '.php',
                    $this->render('search.php', $params)
                );
            }
        }

        return $files;
    }

    protected function getSchemaNames()
    {
        $db = $this->getDbConnection();

        $schema = $db->getSchema();
        if ($schema->hasMethod('getSchemaNames')) { // keep BC to Yii versions < 2.0.4
            try {
                $schemaNames = $schema->getSchemaNames();
            } catch (NotSupportedException $e) {
                // schema names are not supported by schema
            }
        }
        if (!isset($schemaNames)) {
            if (($pos = strpos($this->tableName, '.')) !== false) {
                $schemaNames = [substr($this->tableName, 0, $pos)];
            } else {
                $schemaNames = [''];
            }
        }
        return $schemaNames;
    }

    protected function isManyRelation($table, $fks)
    {
        $uniqueKeys = [$table->primaryKey];
        try {
            $uniqueKeys = array_merge($uniqueKeys, $this->getDbConnection()->getSchema()->findUniqueIndexes($table));
        } catch (NotSupportedException $e) {
            // ignore
        }
        foreach ($uniqueKeys as $uniqueKey) {
            if (count(array_diff(array_merge($uniqueKey, $fks), array_intersect($uniqueKey, $fks))) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array the generated relation declarations
     */
    protected function generateRelations()
    {
        if (!$this->generateRelations) {
            return [];
        }

        $relations = parent::generateRelations();
        $relationNames = [];

        // generate inverse relations

        $db = $this->getDbConnection();

        foreach ($this->getSchemaNames() as $schemaName) {
            foreach ($db->getSchema()->getTableSchemas($schemaName) as $table) {
                $className = $this->generateClassName($table->fullName);
                foreach ($table->foreignKeys as $refs) {
                    $refTable = $refs[0];
                    $refTableSchema = $db->getTableSchema($refTable);
                    unset($refs[0]);
                    $fks = array_keys($refs);

                    $leftRelationName = $this->generateRelationName($relationNames, $table, $fks[0], false);
                    $relationNames[$table->fullName][$leftRelationName] = true;
                    $hasMany = $this->isManyRelation($table, $fks);
                    $rightRelationName = $this->generateRelationName($relationNames, $refTableSchema, $className, $hasMany);
                    $relationNames[$refTableSchema->fullName][$rightRelationName] = true;

                    $relations[$table->fullName][$leftRelationName][0] =
                        rtrim($relations[$table->fullName][$leftRelationName][0], ';')
                        . "->inverseOf('".lcfirst($rightRelationName)."');";
                    $relations[$refTableSchema->fullName][$rightRelationName][0] =
                        rtrim($relations[$refTableSchema->fullName][$rightRelationName][0], ';')
                        . "->inverseOf('".lcfirst($leftRelationName)."');";
                }
            }
        }

        return $relations;
    }

    /**
     * Generates validation rules for the unique indexes of specified table.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated unique validation rules
     */
    public function generateUniqueRules($table)
    {
        $rules = [];
        $uniqueIndexes = $this->getDbConnection()->getSchema()->findUniqueIndexes($table);
        foreach ($uniqueIndexes as $uniqueColumns) {
            // Avoid validating auto incremental columns
            if ($this->isColumnAutoIncremental($table, $uniqueColumns)) {
                continue;
            }
            $attributesCount = count($uniqueColumns);

            if ($attributesCount == 1) {
                $rules[] = "[['" . $uniqueColumns[0] . "'], 'unique']";
            } elseif ($attributesCount > 1) {
                $labels = array_intersect_key($this->generateLabels($table), array_flip($uniqueColumns));
                $lastLabel = array_pop($labels);
                $labels = implode(', ', $labels);
                $columnsList = implode("', '", $uniqueColumns);
                $rules[] = "[
                    ['" . $columnsList . "'], 'unique', 'targetAttribute' => ['" . $columnsList . "'],
                    'message' => Yii::t('app', 'The combination of {labels} and {lastLabel} has already been taken.', [
                        'labels' => '{$labels}',
                        'lastLabel' => '{$lastLabel}'
                    ],
                ]";
            }
        }

        return $rules;
    }

    /**
     * Generates exist rules for foreign key columns.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated exist validation rules
     */
    public function generateExistRules($table)
    {
        $rules = [];
        foreach ($table->foreignKeys as $refs) {
            $refClassName = $this->generateClassName($refs[0]);
            unset($refs[0]);
            $attributes = implode("', '", array_keys($refs));
            $targetAttributes = [];
            foreach ($refs as $key => $value) {
                $targetAttributes[] = "'$key' => '$value'";
            }
            $targetAttributes = implode(', ', $targetAttributes);
            $rules[] = "[['$attributes'], 'exist', 'skipOnError' => true, 'targetClass' => $refClassName::className(), 'targetAttribute' => [$targetAttributes]]";
        }
        return $rules;
    }

    /**
     * @param array $rules
     * @param \yii\db\ColumnSchema $column
     * @param string|bool $behavesAs
     * @return array
     */
    protected function getColumnRules($rules, $column, $behavesAs = false)
    {
        $isRequired = !$column->allowNull && $column->defaultValue === null;

        if ($behavesAs === 'blameableNote') {
            $rules['updateTrim']['attributes'][] = $column->name;
            $rules['updateDefault']['attributes'][] = $column->name;
            if ($isRequired) {
                $rules['updateRequired']['attributes'][] = $column->name;
            }
        } elseif (is_string($behavesAs) && in_array($behavesAs, ['blameable', 'timestamp', 'toggable'])) {
            return $rules;
        } else {
            $rules['trim']['attributes'][] = $column->name;
            $rules['default']['attributes'][] = $column->name;
            if ($isRequired && $behavesAs !== true) {
                $rules['required']['attributes'][] = $column->name;
            }
        }

        switch ($column->type) {
            case Schema::TYPE_PK:
            case Schema::TYPE_BIGPK:
            case Schema::TYPE_SMALLINT:
            case Schema::TYPE_INTEGER:
            case Schema::TYPE_BIGINT:
                if (!strcasecmp($column->name, 'price')) {
                    if (!isset($rules['filterDecimal__2_100'])) {
                        $rules['filterDecimal__2_100']['validator'] = 'filter';
                        $rules['filterDecimal__2_100']['filter'] = "function (\$value) {
                return Yii::\$app->formatter->filterDecimal(\$value, null, 2, 100);
            }";
                    }
                    $rules['filterDecimal__2_100']['attributes'][] = $column->name;
                }
                $isUnsigned = strpos($column->dbType, 'unsigned') !== false;
                $type = '';
                $range = [];
                switch ($column->type) {
                    case Schema::TYPE_SMALLINT:
                        $type = '2';
                        $range['min'] = $isUnsigned ? '0' : '-0x8000';
                        $range['max'] = $isUnsigned ? '0xFFFF' : '0x7FFF';
                        break;
                    case Schema::TYPE_PK:
                    case Schema::TYPE_INTEGER:
                        $type = '4';
                        $range['min'] = $isUnsigned ? '0' : '-0x80000000';
                        $range['max'] = $isUnsigned ? '0xFFFFFFFF' : '0x7FFFFFFF';
                        break;
                    case Schema::TYPE_BIGPK:
                    case Schema::TYPE_BIGINT:
                        $type = '8';
                        break;
                }
                // commented out null detection as this is included in the required rule
                $name = 'int_'.($isUnsigned ? 'u' : '').$type;//.($column->allowNull ? '_null' : '');
                if (!isset($rules[$name])) {
                    $rules[$name] = array_merge($range, [
                        'validator' => 'integer',
                        //'skipOnEmpty' => $column->allowNull ? 'true' : 'false',
                    ]);
                }
                $rules[$name]['attributes'][] = $column->name;
                break;
            case Schema::TYPE_FLOAT:
            case Schema::TYPE_DOUBLE:
            case Schema::TYPE_DECIMAL:
                $rules['number']['attributes'][] = $column->name;
                break;
            case Schema::TYPE_DATETIME:
            case Schema::TYPE_TIMESTAMP:
                $rules['filterDatetime']['attributes'][] = $column->name;
                $rules['formatDatetime']['attributes'][] = $column->name;
                break;
            case Schema::TYPE_TIME:
                $rules['filterTime']['attributes'][] = $column->name;
                $rules['formatTime']['attributes'][] = $column->name;
                break;
            case Schema::TYPE_DATE:
                $rules['filterDate']['attributes'][] = $column->name;
                $rules['formatDate']['attributes'][] = $column->name;
                break;
            // Schema::TYPE_BINARY is treated as string, handled in the default case
            case Schema::TYPE_BOOLEAN:
                $rules['filterBoolean']['attributes'][] = $column->name;
                $rules['boolean']['attributes'][] = $column->name;
                break;
            case Schema::TYPE_MONEY:
                $rules['number']['attributes'][] = $column->name;
                break;
            default: // strings
                if ($column->dbType === 'interval') {
                    $rules['filterInterval']['attributes'][] = $column->name;
                    $rules['interval']['attributes'][] = $column->name;
                    break;
                }
                if ($column->size > 0) {
                    $rules['lengths' . $column->size]['validator'] = 'string';
                    $rules['lengths' . $column->size]['max'] = $column->size;
                    $rules['lengths' . $column->size]['attributes'][] = $column->name;
                } elseif (!strcasecmp($column->name, 'email')) {
                    $rules['email']['attributes'][] = $column->name;
                } else {
                    $rules['safe']['attributes'][] = $column->name;
                }
                break;
        }
        return $rules;
    }

    /**
     * Generates validation rules for the specified table.
     * @param \yii\db\TableSchema $table the table schema
     * @param bool $isSearchScenario if false, special columns will be unsafe (no rules)
     * @return array the generated validation rules
     */
    public function generateRules($table, $isSearchScenario = false)
    {
        $behaviors = $this->generateBehaviors($table);

        /** @var array $rules predefine only rules with names different than the validator */
        $rules = [
            'trim' => [
                'attributes' => [],
            ],
            'default' => [
                'attributes' => [],
            ],
            'required' => [
                'attributes' => [],
            ],
            'updateTrim' => [
                'validator' => 'trim',
                'on' => 'update',
            ],
            'updateDefault' => [
                'validator' => 'default',
                'on' => 'update',
            ],
            'updateRequired' => [
                'validator' => 'required',
                'on' => 'update',
            ],
            'filterDatetime' => [
                'validator' => 'filter',
                'filter' => "[Yii::\$app->formatter, 'filterDatetime']",
            ],
            'filterDate' => [
                'validator' => 'filter',
                'filter' => "[Yii::\$app->formatter, 'filterDate']",
            ],
            'filterTime' => [
                'validator' => 'filter',
                'filter' => "[Yii::\$app->formatter, 'filterTime']",
            ],
            'formatDatetime' => [
                'validator' => 'date',
                'format' => "'yyyy-MM-dd HH:mm:ss'",
            ],
            'formatTimestamp' => [
                'validator' => 'date',
                'format' => "'yyyy-MM-dd HH:mm:ss'",
            ],
            'formatDate' => [
                'validator' => 'date',
                'format' => "'yyyy-MM-dd'",
            ],
            'formatTime' => [
                'validator' => 'date',
                'format' => "'HH:mm:ss'",
            ],
            'filterBoolean' => [
                'validator' => 'filter',
                'filter' => "[Yii::\$app->formatter, 'filterBoolean']",
            ],
            'filterInterval' => [
                'validator' => 'filter',
                'filter' => "[Yii::\$app->formatter, 'filterInterval']",
            ],
            'interval' => [
                'validator' => 'netis\utils\validators\IntervalValidator',
            ],
            'safe' => [
                'attributes' => [],
            ],
        ];

        foreach ($table->columns as $column) {
            if ($column->autoIncrement) {
                continue;
            }

            // assume special attributes will ALWAYS be filled automatically and are never required by user
            $behavesAs = false;
            foreach ($behaviors as $behaviorName => $behavior) {
                foreach ($behavior['options'] as $option) {
                    if ($option === $column->name) {
                        if ($option === 'updateNotesAttribute') {
                            $behavesAs = 'blameableNote';
                        } else {
                            $behavesAs = $behaviorName;
                        }
                        break 2;
                    }
                }
            }

            $rules = $this->getColumnRules($rules, $column, $isSearchScenario ? true : $behavesAs);
        }

        // remove safe attributes that have any other rules without a specific scenario
        $safeAttributes = [];
        foreach ($rules as $rule) {
            if (isset($rule['on']) || !isset($rule['attributes']) || empty($rule['attributes'])) {
                continue;
            }
            $safeAttributes += $rule['attributes'];
        }
        $safeAttributes = array_flip($safeAttributes);
        foreach ($rules['safe']['attributes'] as $key => $attribute) {
            if (isset($safeAttributes[$attribute])) {
                unset($rules['safe']['attributes'][$key]);
            }
        }

        $result = [];
        foreach ($rules as $ruleName => $rule) {
            if (!isset($rule['attributes']) || empty($rule['attributes'])) {
                continue;
            }
            $attributes = $rule['attributes'];
            unset($rule['attributes']);
            $validator = $ruleName;
            if (isset($rule['validator'])) {
                $validator = $rule['validator'];
                unset($rule['validator']);
            }
            $params = '';
            if (!empty($rule)) {
                $params = ', ' . implode(', ', array_map(function ($k, $v) {
                    return "'$k' => $v";
                }, array_keys($rule), array_values($rule)));
            }
            if ($ruleName === 'formatTimestamp') {
                foreach ($attributes as $attribute) {
                    $result[] = "[['$attribute'], '$validator'{$params}, 'timestampAttribute' => '$attribute']";
                }
            } else {
                $result[] = "[['" . implode("', '", $attributes) . "'], '$validator'{$params}]";
            }
        }

        try {
            $result += $this->generateUniqueRules($table);
        } catch (NotSupportedException $e) {
            // doesn't support unique indexes information...do nothing
        }

        return $result + $this->generateExistRules($table);
    }

    /**
     * Generates behaviors for the specified table, detecting special columns.
     * @param \yii\db\TableSchema $table the table schema
     * @param string $className
     * @return array the generated behaviors as name => options
     */
    public function generateBehaviors($table)
    {
        $available = [
            [
                'name' => 'blameable',
                'attributes' => ['author_id', 'created_by', 'created_id'],
                'class' => 'netis\utils\db\BlameableBehavior',
                'optionName' => 'createdByAttribute',
            ],
            [
                'name' => 'blameable',
                'attributes' => ['editor_id', 'edited_by', 'updated_by', 'updated_id', 'last_editor_id'],
                'class' => 'netis\utils\db\BlameableBehavior',
                'optionName' => 'updatedByAttribute',
            ],
            [
                'name' => 'blameable',
                'attributes' => ['update_reason'],
                'class' => 'netis\utils\db\BlameableBehavior',
                'optionName' => 'updateNotesAttribute',
            ],
            [
                'name' => 'timestamp',
                'attributes' => ['created_on', 'created_at', 'create_at', 'created_date', 'date_created'],
                'class' => 'netis\utils\db\TimestampBehavior',
                'optionName' => 'createdAtAttribute',
            ],
            [
                'name' => 'timestamp',
                'attributes' => ['updated_on', 'updated_at', 'update_at', 'updated_date', 'date_updated'],
                'class' => 'netis\utils\db\TimestampBehavior',
                'optionName' => 'updatedAtAttribute',
            ],
            [
                'name' => 'toggable',
                'attributes' => [
                    'is_disabled', 'disabled', 'is_deleted', 'deleted', 'is_removed', 'removed', 'is_hidden', 'hidden',
                ],
                'class' => 'netis\utils\db\ToggableBehavior',
                'optionName' => 'disabledAttribute',
            ],
            [
                'name' => 'toggable',
                'attributes' => ['is_enabled', 'enabled', 'is_active', 'active', 'is_visible', 'visible'],
                'class' => 'netis\utils\db\ToggableBehavior',
                'optionName' => 'enabledAttribute',
            ],
            [
                'name' => 'sortable',
                'attributes' => ['display_order', 'sort_order'],
                'class' => 'netis\utils\db\SortableBehavior',
                'optionName' => 'attribute',
            ],
        ];
        $behaviors = [
            'labels' => [
                'class' => 'netis\utils\db\LabelsBehavior',
                'options' => [
                    'attributes' => [$this->getLabelAttribute($table)],
                ],
            ],
        ];
        foreach ($table->columns as $column) {
            foreach ($available as $options) {
                if (in_array($column->name, $options['attributes'])) {
                    $behaviors[$options['name']]['class'] = $options['class'];
                    $behaviors[$options['name']]['options'][$options['optionName']] = $column->name;
                    break;
                }
            }
        }
        return $behaviors;
    }

    /**
     * Finds the label attribute.
     * @param \yii\db\TableSchema $table the table schema
     * @return string name of the label attribute
     */
    public function getLabelAttribute($table)
    {
        $possible = [
            0 => 'label',
            1 => 'title',
            2 => 'name',
            3 => 'symbol',
        ];
        $labels = [
            0 => null,
            1 => null,
            2 => null,
            3 => null,
        ];
        $primaryKeys = [];
        foreach ($table->columns as $column) {
            if ($column->isPrimaryKey) {
                $primaryKeys[] = $column->name;
            }
            foreach ($possible as $weight => $possibleLabel) {
                if (!strcasecmp($column->name, $possibleLabel)) {
                    $labels[$weight] = $column->name;
                }
            }
            if ($column->type === 'string') {
                array_push($labels, $column->name);
            }
        }

        if (($primaryKey = reset($primaryKeys)) !== false) {
            array_push($labels, $primaryKey);
        }

        foreach ($table->columns as $column) {
            array_push($labels, $column->name);
        }

        ksort($labels);

        while (($label = array_shift($labels)) !== false) {
            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @return array the table names that match the pattern specified by [[tableName]].
     */
    protected function getTableNames()
    {
        if ($this->tableNames !== null) {
            return $this->tableNames;
        }
        $db = $this->getDbConnection();
        if ($db === null) {
            return [];
        }
        $tableNames = [];
        if (strpos($this->tableName, '*') !== false) {
            if (($pos = strrpos($this->tableName, '.')) !== false) {
                $schema = substr($this->tableName, 0, $pos);
                if ($schema === $db->schema->defaultSchema) {
                    $schema = '';
                }
                $pattern = '/^' . str_replace('*', '\w+', substr($this->tableName, $pos + 1)) . '$/';
            } else {
                $schema = '';
                $pattern = '/^' . str_replace('*', '\w+', $this->tableName) . '$/';
            }

            foreach ($db->schema->getTableNames($schema) as $table) {
                if (preg_match($pattern, $table)) {
                    $tableNames[] = $schema === '' ? $table : ($schema . '.' . $table);
                }
            }
        } elseif (($table = $db->getTableSchema($this->tableName, true)) !== null) {
            $tableNames[] = $this->tableName;
            $this->classNames[$this->tableName] = $this->modelClass;
        }

        return $this->tableNames = $tableNames;
    }

    /**
     * Generates a class name from the specified table name.
     * @param string $tableName the table name (which may contain schema prefix)
     * @param boolean $useSchemaName should schema name be included in the class name, if present
     * @return string the generated class name
     */
    protected function baseGenerateClassName($tableName, $useSchemaName = null)
    {
        $schemaName = '';
        if (($pos = strrpos($tableName, '.')) !== false) {
            if (($useSchemaName === null && $this->useSchemaName) || $useSchemaName) {
                $schemaName = substr($tableName, 0, $pos) . '_';
            }
            $tableName = substr($tableName, $pos + 1);
        }

        $db = $this->getDbConnection();
        $patterns = [];
        $patterns[] = "/^{$db->tablePrefix}(.*?)$/";
        $patterns[] = "/^(.*?){$db->tablePrefix}$/";
        if (strpos($this->tableName, '*') !== false) {
            $pattern = $this->tableName;
            if (($pos = strrpos($pattern, '.')) !== false) {
                $pattern = substr($pattern, $pos + 1);
            }
            $patterns[] = '/^' . str_replace('*', '(\w+)', $pattern) . '$/';
        }
        $className = $tableName;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $tableName, $matches)) {
                $className = $matches[1];
                break;
            }
        }

        return Inflector::id2camel($schemaName.$className, '_');
    }

    /**
     * Generates a class name from the specified table name.
     * @param string $tableName the table name (which may contain schema prefix)
     * @param boolean $useSchemaName should schema name be included in the class name, if present
     * @return string the generated class name
     */
    protected function generateClassName($tableName, $useSchemaName = null)
    {
        if (isset($this->classNames[$tableName])) {
            return $this->classNames[$tableName];
        }

        $className = $this->baseGenerateClassName($tableName, $useSchemaName);
        return $this->classNames[$tableName] = Inflector::singularize($className);
    }

    /**
     * Generates a search class name from the specified model class name.
     * @param string $modelClassName model class name
     * @return string generated class name
     */
    protected function generateSearchClassName($modelClassName)
    {
        $searchClassName = $this->searchModelClass;
        if (empty($searchClassName) || strpos($this->tableName, '*') !== false) {
            $searchClassName = $modelClassName;
        }
        return $searchClassName;
    }
}


