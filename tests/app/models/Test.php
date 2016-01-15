<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%test}}".
 *
 * @property integer $id
 * @property string $required_field
 * @property string $text
 * @property boolean $boolean
 * @property integer $length
 * @property integer $weight
 * @property integer $multiplied_100
 * @property integer $multiplied_1000
 * @property integer $integer
 * @property string $time
 * @property string $datetime
 * @property string $date
 * @property integer $enum
 * @property integer $flags
 * @property string $paragraphs
 * @property string $file
 * @property resource $other
 */
class Test extends \netis\crud\db\ActiveRecord
{
    const ENUM_1 = 1;
    const ENUM_2 = 2;
    const ENUM_3 = 3;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%test}}';
    }

    /**
     * @inheritdoc
     */
    public function filteringRules()
    {
        return [
            [['required_field', 'text', 'boolean', 'length', 'weight', 'multiplied_100', 'multiplied_1000', 'integer', 'time', 'datetime', 'date', 'enum', 'flags', 'paragraphs', 'file', 'other'], 'trim'],
            [['required_field', 'text', 'boolean', 'length', 'weight', 'multiplied_100', 'multiplied_1000', 'integer', 'time', 'datetime', 'date', 'enum', 'flags', 'paragraphs', 'file', 'other'], 'default'],
            [['datetime'], 'filter', 'filter' => [Yii::$app->formatter, 'filterDatetime']],
            [['date'], 'filter', 'filter' => [Yii::$app->formatter, 'filterDate']],
            [['time'], 'filter', 'filter' => [Yii::$app->formatter, 'filterTime']],
            [['boolean'], 'filter', 'filter' => [Yii::$app->formatter, 'filterBoolean']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['required_field'], 'required'],
            [['datetime'], 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
            [['date'], 'date', 'format' => 'yyyy-MM-dd'],
            [['time'], 'date', 'format' => 'HH:mm:ss'],
            [['paragraphs', 'file', 'other'], 'safe'],
            [['required_field', 'text'], 'string', 'max' => 255],
            [['boolean'], 'boolean'],
            [['length', 'weight', 'multiplied_100', 'multiplied_1000', 'integer', 'enum', 'flags'], 'integer', 'min' => -0x80000000, 'max' => 0x7FFFFFFF],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'required_field' => 'Required Field',
            'text' => 'Text',
            'boolean' => 'Boolean',
            'length' => 'Length',
            'weight' => 'Weight',
            'multiplied_100' => 'Multiplied 100',
            'multiplied_1000' => 'Multiplied 1000',
            'integer' => 'Integer',
            'time' => 'Time',
            'datetime' => 'Datetime',
            'date' => 'Date',
            'enum' => 'Enum',
            'flags' => 'Flags',
            'paragraphs' => 'Paragraphs',
            'file' => 'File',
            'other' => 'Other',
        ];
    }

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'labels' => [
                'class' => 'netis\crud\db\LabelsBehavior',
                'attributes' => ['required_field'],
                'crudLabels' => [
                    'default'  => 'Test',
                    'relation' => 'Tests',
                    'index'    => 'Browse Tests',
                    'create'   => 'Create Test',
                    'read'     => 'View Test',
                    'update'   => 'Update Test',
                    'delete'   => 'Delete Test',
                ],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function relations()
    {
        return [
        ];
    }

    public function loadDefaultValues($skipIfSet = true)
    {
        $this->setAttributes([
            'text'            => 'test',
            'boolean'         => 'true',
            'length'          => 2000,
            'weight'          => 1200,
            'multiplied_100'  => 120,
            'multiplied_1000' => 120,
            'integer'         => 23,
            'time'            => '12:30',
            'datetime'        => '2016-01-23 12:30',
            'date'            => '2016-01-23',
            'enum'            => 1,
            'paragraphs'      => "abc\ntest",
            'other'           => 'test',
        ]);

        return parent::loadDefaultValues($skipIfSet);
    }

    public function attributeFormats()
    {
        return array_merge(parent::attributeFormats(), [
            'length'          => 'shortLength',
            'weight'          => 'shortWeight',
            'multiplied_100'  => ['multiplied', 100],
            'multiplied_1000' => ['multiplied', 1000],
            'enum'            => ['enum', 'testEnumType'],
            'other'           => 'other',
            'file'            => 'file',
            'flags'           => 'flags',
        ]);
    }
}
