<?php
/**
 * This is the template for generating CRUD search class of the specified model.
 */

use yii\helpers\Inflector;

/* @var $this yii\web\View */
/* @var $generator netis\utils\generators\model\Generator */
/* @var $className string class name */
/* @var $modelClassName string related model class name */
/* @var $queryClassName string query model class name */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */
/* @var $relations array list of relations (name => relation declaration) */

$modelFullClassName = $modelClassName;
if ($generator->ns !== $generator->searchNs) {
    $modelFullClassName = '\\' . $generator->ns . '\\' . $modelFullClassName;
}
if ($modelClassName === $className) {
    $modelAlias = $modelClassName . 'Model';
}

echo "<?php\n";
?>

namespace <?= $generator->searchNs ?>;

use Yii;
use yii\base\Model;
use <?= ltrim($modelFullClassName, '\\') . (isset($modelAlias) ? " as $modelAlias" : "") ?>;
use <?= ($queryClassName === 'ActiveQuery' ? 'yii\db' : $generator->queryNs) . '\\' . $queryClassName ?>;
use netis\utils\db\ActiveSearchInterface;

/**
 * <?= $className ?> represents the model behind the search form about `<?= $modelFullClassName ?>`.
 */
class <?= $className ?> extends <?= isset($modelAlias) ? $modelAlias : $modelClass ?> implements ActiveSearchInterface

{
    use \netis\utils\db\ActiveSearchTrait;

<?php foreach ($relations as $name => $relation): if (true || !$relation[2]) continue; ?>
    /**
     * @var string <?= $name ?> relation keys
     */
    public $<?= lcfirst($name) ?>;

<?php endforeach; ?>
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            <?= implode(",\n            ", $rules) ?>,
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * @inheritdoc
     * @return <?= $queryClassName ?> the active query used by this AR class.
     */
    public static function find()
    {
        return new <?= $queryClassName ?>('<?= ltrim($modelFullClassName, '\\') ?>');
    }
}
