<?php
/**
 * This is the template for generating the model class of a specified table.
 */

/* @var $this yii\web\View */
/* @var $generator netis\utils\generators\model\Generator */
/* @var $tableName string full table name */
/* @var $className string class name */
/* @var $queryClassName string query class name */
/* @var $tableSchema yii\db\TableSchema */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */
/* @var $relations array list of relations (name => relation declaration) */
/* @var $behaviors array list of behaviors (name => behavior declaration) */

$queryClassFullName = ($generator->ns === $generator->queryNs) ? $queryClassName : '\\' . $generator->queryNs . '\\' . $queryClassName;

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
    public function rules()
    {
        return [<?= "\n            " . implode(",\n            ", $rules) . "\n        " ?>];
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
        return array_merge([
<?php foreach ($behaviors as $name => $behavior): ?>
            '<?= $name ?>' => [
                'class' => '<?= $behavior['class'] ?>',
<?php foreach ($behavior['options'] as $option => $optionValue): ?>
                <?= "'$option' => " . (is_array($optionValue)
                    ? "['" . implode("', '", $optionValue) . "']"
                    : "'{$optionValue}'") ?>,
<?php endforeach; ?>
            ],
<?php endforeach; ?>
        ], parent::behaviors());
    }

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
     * @return <?= ($queryClassName ? $queryClassName : '\yii\db\ActiveQuery')."\n" ?>
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
