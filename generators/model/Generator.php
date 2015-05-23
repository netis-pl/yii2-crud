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
        $rules = parent::rules();
        foreach ($rules as $key => $rule) {
            $attributes = array_shift($rule);
            $validator = array_shift($rule);
            if ((in_array('ns', $attributes) || in_array('queryNs', $attributes))
                && $validator === 'match' && isset($rule['pattern']) && $rule['pattern'] === '/^[\w\\\\]+$/'
            ) {
                $rules[$key][0] = array_diff($attributes, ['ns', 'queryNs']);
                $rules[] = [
                    ['ns', 'queryNs'],
                    'match',
                    'pattern' => '/^[\w\\\\\\-]+$/',
                    'message' => $rule['message'],
                ];
            }
        }
        return array_merge($rules, [
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
                'pattern' => '/^[\w\\\\\\-]+$/',
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
                    'labels' => $this->generateSearchLabels($tableSchema),
                    'rules' => $this->generateSearchRules($tableSchema),
                ];
                $files[] = new CodeFile(
                    Yii::getAlias('@' . str_replace('\\', '/', $this->searchNs)) . '/' . $searchClassName . '.php',
                    $this->render('search.php', $params)
                );
            }
        }

        return $files;
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
     * Generates validation rules for the specified table.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated validation rules
     */
    public function generateRules($table)
    {
        $types = [];
        $lengths = [];
        foreach ($table->columns as $column) {
            if ($column->autoIncrement) {
                continue;
            }
            if (!$column->allowNull && $column->defaultValue === null) {
                $types['required'][] = $column->name;
            }
            switch ($column->type) {
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                    $types['integer'][] = $column->name;
                    break;
                case Schema::TYPE_BOOLEAN:
                    $types['boolean'][] = $column->name;
                    break;
                case Schema::TYPE_FLOAT:
                case 'double': // Schema::TYPE_DOUBLE, which is available since Yii 2.0.3
                case Schema::TYPE_DECIMAL:
                case Schema::TYPE_MONEY:
                    $types['number'][] = $column->name;
                    break;
                case Schema::TYPE_DATE:
                case Schema::TYPE_TIME:
                case Schema::TYPE_DATETIME:
                case Schema::TYPE_TIMESTAMP:
                    $types['safe'][] = $column->name;
                    break;
                default: // strings
                    if ($column->size > 0) {
                        $lengths[$column->size][] = $column->name;
                    } else {
                        $types['string'][] = $column->name;
                    }
            }
        }
        $rules = [];
        foreach ($types as $type => $columns) {
            $rules[] = "[['" . implode("', '", $columns) . "'], '$type']";
        }
        foreach ($lengths as $length => $columns) {
            $rules[] = "[['" . implode("', '", $columns) . "'], 'string', 'max' => $length]";
        }

        try {
            $rules += $this->generateUniqueRules($table);
        } catch (NotSupportedException $e) {
            // doesn't support unique indexes information...do nothing
        }

        return $rules;
    }

    /**
     * Generates behaviors for the specified table, detecting special columns.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated behaviors as name => options
     */
    public function generateBehaviors($table)
    {
        $available = [
            [
                'name' => 'blameable',
                'attributes' => ['author_id', 'created_by', 'created_id'],
                'class' => 'yii\behaviors\BlameableBehavior',
                'optionName' => 'createdByAttribute',
            ],
            [
                'name' => 'blameable',
                'attributes' => ['editor_id', 'edited_by', 'updated_by', 'updated_id', 'last_editor_id'],
                'class' => 'yii\behaviors\BlameableBehavior',
                'optionName' => 'updatedByAttribute',
            ],
            [
                'name' => 'timestamp',
                'attributes' => ['created_on', 'created_at', 'create_at', 'created_date', 'date_created'],
                'class' => 'yii\behaviors\TimestampBehavior',
                'optionName' => 'createdAtAttribute',
            ],
            [
                'name' => 'togglable',
                'attributes' => [
                    'is_disabled', 'disabled', 'is_deleted', 'deleted', 'is_removed', 'removed', 'is_hidden', 'hidden',
                ],
                'class' => 'netis\utils\db\ToggableBehavior',
                'optionName' => 'disabledAtAttribute',
            ],
            [
                'name' => 'togglable',
                'attributes' => ['is_enabled', 'enabled', 'is_active', 'active', 'is_visible', 'visible'],
                'class' => 'netis\utils\db\ToggableBehavior',
                'optionName' => 'enabledAtAttribute',
            ],
            [
                'name' => 'sortable',
                'attributes' => ['display_order', 'sort_order'],
                'class' => 'netis\utils\db\SortableBehavior',
                'optionName' => 'attribute',
            ],
        ];
        $behaviors = [
            'string' => [
                'class' => 'netis\utils\db\StringBehavior',
                'options' => [
                    'attributes' => ["'" . $this->getLabelAttribute($table) . "'"],
                ],
            ],
        ];
        foreach ($table->columns as $column) {
            foreach ($available as $options) {
                if (in_array($column->name, $options['attributes'])) {
                    $behaviors[$options['name']] = ['class' => $options['class']];
                    $behaviors['blameable']['options'][$options['optionName']] = "'{$column->name}'";
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
        foreach ($table->columns as $column) {
            foreach ($possible as $weight => $possibleLabel) {
                if (!strcasecmp($column->name, $possibleLabel)) {
                    $labels[$weight] = $column->name;
                }
            }
            if ($column->type === 'string') {
                array_push($labels, $column->name);
            }
        }

        /* @var $class \yii\db\ActiveRecord */
        $class = $this->modelClass;
        $pk = $class::primaryKey();
        array_push($labels, $pk[0]);

        foreach ($table->columns as $column) {
            array_push($labels, $column->name);
        }

        ksort($labels);

        while (($label = reset($labels)) !== false) {
            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Generates validation rules for the search model.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated validation rules
     */
    public function generateSearchRules($table)
    {
        $types = [];
        foreach ($table->columns as $column) {
            switch ($column->type) {
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                    $types['integer'][] = $column->name;
                    break;
                case Schema::TYPE_BOOLEAN:
                    $types['boolean'][] = $column->name;
                    break;
                case Schema::TYPE_FLOAT:
                case Schema::TYPE_DOUBLE:
                case Schema::TYPE_DECIMAL:
                case Schema::TYPE_MONEY:
                    $types['number'][] = $column->name;
                    break;
                case Schema::TYPE_DATE:
                case Schema::TYPE_TIME:
                case Schema::TYPE_DATETIME:
                case Schema::TYPE_TIMESTAMP:
                default:
                    $types['safe'][] = $column->name;
                    break;
            }
        }

        $rules = [];
        foreach ($types as $type => $columns) {
            $rules[] = "[['" . implode("', '", $columns) . "'], '$type']";
        }

        return $rules;
    }

    /**
     * @param \yii\db\TableSchema $table the table schema
     * @return array searchable attributes
     */
    public function getSearchAttributes($table)
    {
        return $table->getColumnNames();
    }

    /**
     * Generates the attribute labels for the search model.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated attribute labels (name => label)
     */
    public function generateSearchLabels($table)
    {
        /* @var $model \yii\base\Model */
        $model = new $this->modelClass();
        $attributeLabels = $model->attributeLabels();
        $labels = [];
        foreach ($table->getColumnNames() as $name) {
            if (isset($attributeLabels[$name])) {
                $labels[$name] = $attributeLabels[$name];
            } else {
                if (!strcasecmp($name, 'id')) {
                    $labels[$name] = 'ID';
                } else {
                    $label = Inflector::camel2words($name);
                    if (!empty($label) && substr_compare($label, ' id', -3, 3, true) === 0) {
                        $label = substr($label, 0, -3) . ' ID';
                    }
                    $labels[$name] = $label;
                }
            }
        }

        return $labels;
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

