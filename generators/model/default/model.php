<?php
/**
 * This is the template for generating the model class of a specified table.
 */
use yii\helpers\Inflector;

/* @var $this yii\web\View */
/* @var $generator netis\crud\generators\model\Generator */
/* @var $tableName string full table name */
/* @var $className string class name */
/* @var $queryClassName string query class name */
/* @var $tableSchema yii\db\TableSchema */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */
/* @var $filterRules string[] list of filtering rules */
/* @var $relations array list of relations (name => relation declaration) */
/* @var $behaviors array list of behaviors (name => behavior declaration) */

$queryClassFullName = ($generator->ns === $generator->queryNs) ? $queryClassName : '\\' . $generator->queryNs . '\\' . $queryClassName;
$regexp = '/(?#! splitCamelCase Rev:20140412)
    # Split camelCase "words". Two global alternatives. Either g1of2:
      (?<=[a-z])      # Position is after a lowercase,
      (?=[A-Z])       # and before an uppercase letter.
    | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
      (?=[A-Z][a-z])  # and before upper-then-lower case.
    /x';
$classLabel = implode(' ', preg_split($regexp, $className));
$versionAttribute = null;
if (isset($behaviors['versioned'])) {
    $versionAttribute = $behaviors['versioned'];
    unset($behaviors['versioned']);
}

echo "<?php\n";
?>

namespace <?= $generator->ns ?>;

use Yii;
<?php if ($queryClassName && $generator->ns !== $generator->queryNs): ?>
use <?= $generator->queryNs . '\\' . $queryClassName; ?>;
<?php endif; ?>

/**
 * This is the model class for table "<?= $generator->generateTableName($tableName) ?>".
 *
<?php foreach ($tableSchema->columns as $column): ?>
 * @property <?= "{$column->phpType} \${$column->name}\n" ?>
<?php endforeach; ?>
<?php if (!empty($relations)): ?>
 *
<?php foreach ($relations as $name => $relation): ?>
 * @property <?= $relation[1] . ($relation[2] ? '[]' : '') . ' $' . lcfirst($name) . "\n" ?>
<?php endforeach; ?>
<?php endif; ?>
 */
class <?= $className ?> extends <?= '\\' . ltrim($generator->baseClass, '\\') . "\n" ?>
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '<?= $generator->generateTableName($tableName) ?>';
    }
<?php if ($generator->db !== 'db'): ?>

    /**
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->get('<?= $generator->db ?>');
    }
<?php endif; ?>

    /**
     * @inheritdoc
     */
    public function filteringRules()
    {
        return [<?= "\n            " . implode("\n            ", $filterRules) . "\n        " ?>];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [<?= "\n            " . implode("\n            ", $rules) . "\n        " ?>];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
<?php foreach ($labels as $name => $label): ?>
            <?= "'$name' => " . $generator->generateString($label) . ",\n" ?>
<?php endforeach; ?>
        ];
    }

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
<?php foreach ($behaviors as $name => $behavior): ?>
            '<?= $name ?>' => [
                'class' => \<?= $behavior['class'] ?>::className(),
<?php foreach ($behavior['options'] as $option => $optionValue): ?>
                <?= "'$option' => " . (is_array($optionValue)
                    ? "[" . implode(', ', array_map(
                        function ($k, $v) {
                            return is_numeric($k) ? "'$v'" : "'$k' => '$v'";
                        },
                        array_keys($optionValue),
                        array_values($optionValue)
                    )) . "]"
                    : "'{$optionValue}'") ?>,
<?php if ($name === 'labels'): ?>
                'crudLabels' => [
                    'default'  => <?= $generator->generateString($classLabel) ?>,
                    'relation' => <?= $generator->generateString(Inflector::pluralize($classLabel)) ?>,
                    'index'    => <?= $generator->generateString('Browse '.Inflector::pluralize($classLabel)) ?>,
                    'create'   => <?= $generator->generateString('Create '.$classLabel) ?>,
                    'read'     => <?= $generator->generateString('View '.$classLabel) ?>,
                    'update'   => <?= $generator->generateString('Update '.$classLabel) ?>,
                    'delete'   => <?= $generator->generateString('Delete '.$classLabel) ?>,
                ],
<?php endif; ?>
<?php endforeach; ?>
            ],
<?php endforeach; ?>
        ]);
    }
<?php if ($versionAttribute !== null): ?>

    /**
     * @inheritdoc
     */
    public function optimisticLock()
    {
        return '<?= $versionAttribute ?>';
    }
<?php endif; ?>

    /**
     * @inheritdoc
     */
    public static function relations()
    {
        return [
<?php foreach ($relations as $name => $relation): ?>
            '<?= lcfirst($name) ?>',
<?php endforeach; ?>
        ];
    }
<?php foreach ($relations as $name => $relation): ?>

    /**
     * @return <?= $relation[1]."Query\n" ?>
     */
    public function get<?= $name ?>()
    {
        <?= $relation[0] . "\n" ?>
    }
<?php endforeach; ?>
<?php if ($queryClassName): ?>

    /**
     * @inheritdoc
     * @return <?= $queryClassName ?> the active query used by this AR class.
     */
    public static function find()
    {
        return new <?= $queryClassName ?>(get_called_class());
    }
<?php endif; ?>
}
