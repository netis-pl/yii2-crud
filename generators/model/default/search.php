<?php
/**
 * This is the template for generating CRUD search class of the specified model.
 */

use yii\helpers\Inflector;

/* @var $this yii\web\View */
/* @var $generator netis\utils\generators\model\Generator */
/* @var $className string class name */
/* @var $modelClassName string related model class name */
/* @var $labels string[] list of attribute labels (name => label) */
/* @var $rules string[] list of validation rules */

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

/**
 * <?= $className ?> represents the model behind the search form about `<?= $modelFullClassName ?>`.
 */
class <?= $className ?> extends <?= isset($modelAlias) ? $modelAlias : $modelClass ?>

{
    use \netis\utils\db\ActiveSearchTrait;

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
}
