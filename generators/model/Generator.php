<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\generators\model;

use Yii;
use yii\helpers\Inflector;

class Generator extends \yii\gii\generators\model\Generator
{
    public $singularModelClass = true;

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
        foreach($rules as $key => $rule) {
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
        ]);
    }

    /**
     * @inheritdoc
     */
    public function hints()
    {
        return array_merge(parent::hints(), [
            'singularModelClass' => 'If checked, will generate singular model class from a plural table name.',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function stickyAttributes()
    {
        return array_merge(parent::stickyAttributes(), ['singularModelClass']);
    }

    /**
     * Returns the root path to the default code template files.
     * The default implementation will return the "templates" subdirectory of the
     * directory containing the generator class file.
     * @return string the root path to the default code template files.
     */
    public function defaultTemplate()
    {
        $class = new \ReflectionClass(get_parent_class());

        return dirname($class->getFileName()) . '/default';
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
}

