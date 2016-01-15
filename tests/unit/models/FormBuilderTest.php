<?php
/**
 * Created by PhpStorm.
 * User: michal
 * Date: 15.01.16
 * Time: 19:57
 */

namespace tests\codeception\unit\models;


use app\models\Test;
use kartik\datetime\DateTimePicker;
use netis\crud\widgets\FormBuilder;
use omnilight\widgets\DatePicker;
use Yii;
use yii\bootstrap\ActiveField;
use yii\bootstrap\Html;
use yii\codeception\TestCase;
use Codeception\Specify;

class FormBuilderTest extends TestCase
{
    use Specify;
    public $appConfig = '@tests/config/unit.php';

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testGeneratingFormFields()
    {
        $model = new Test();
        $model->loadDefaultValues();

        $this->specify('Model has proper attribute formats', function () use ($model) {
            expect($model->getAttributeFormat('text'))->equals('text');
            expect($model->getAttributeFormat('boolean'))->equals('boolean');
            expect($model->getAttributeFormat('length'))->equals('shortLength');
            expect($model->getAttributeFormat('weight'))->equals('shortWeight');
            expect($model->getAttributeFormat('multiplied_100'))->equals(['multiplied', 100]);
            expect($model->getAttributeFormat('multiplied_1000'))->equals(['multiplied', 1000]);
            expect($model->getAttributeFormat('integer'))->equals('integer');
            expect($model->getAttributeFormat('time'))->equals('time');
            expect($model->getAttributeFormat('datetime'))->equals('datetime');
            expect($model->getAttributeFormat('date'))->equals('date');
            expect($model->getAttributeFormat('enum'))->equals(['enum', 'testEnumType']);
            expect($model->getAttributeFormat('paragraphs'))->equals('paragraphs');
            expect($model->getAttributeFormat('other'))->equals('other');
        });

        $this->specify('FormBuilder generates field for text attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'text');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('text');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-text" class="form-control" name="Test[text]" value="test" maxlength="255">');
        });

        $this->specify('FormBuilder generates field for boolean attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'boolean');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('boolean');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="hidden" name="Test[boolean]" value="0"><input type="checkbox" id="test-boolean" name="Test[boolean]" value="1">');

            $field = FormBuilder::createActiveField($model, 'boolean', [], true);

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('boolean');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals(<<<Html
<input type="hidden" name="Test[boolean]" value=""><div id="test-boolean"><label class="radio-inline"><input type="radio" name="Test[boolean]" value="0"> No</label>
<label class="radio-inline"><input type="radio" name="Test[boolean]" value="1"> Yes</label>
<label class="radio-inline"><input type="radio" name="Test[boolean]" value=""> Any</label></div>
Html
);
        });

        $this->specify('FormBuilder generates field for shortLength attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'length');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('length');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-length" class="form-control" name="Test[length]" value="2">');
        });

        $this->specify('FormBuilder generates field for shortWeight attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'weight');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('weight');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-weight" class="form-control" name="Test[weight]" value="1.2">');
        });

        $this->specify('FormBuilder generates field for multiplied attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'multiplied_100');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('multiplied_100');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-multiplied_100" class="form-control" name="Test[multiplied_100]" value="1.2">');

            $field = FormBuilder::createActiveField($model, 'multiplied_1000');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('multiplied_1000');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-multiplied_1000" class="form-control" name="Test[multiplied_1000]" value="0.12">');
        });

        $this->specify('FormBuilder generates field for integer attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'integer');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('integer');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-integer" class="form-control" name="Test[integer]" value="23">');
        });

        $this->specify('FormBuilder generates field for time attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'time');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('time');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-time" class="form-control" name="Test[time]" value="12:30">');
        });

        $this->specify('FormBuilder generates field for datetime attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'datetime');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('datetime');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals([
                'model' => $model,
                'class' => DateTimePicker::className(),
                'attribute' => 'datetime',
                'options' => ['value'=>'23-01-2016 12:30:00', 'class' => 'form-control'],
                'convertFormat' => true,
            ]);
        });

        $this->specify('FormBuilder generates field for date attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'date');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('date');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals([
                'model' => $model,
                'class' => DatePicker::className(),
                'attribute' => 'date',
                'options' => ['value' => '23-01-2016', 'class' => 'form-control'],
            ]);
        });

        $this->specify('FormBuilder generates field for enum attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'enum');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('enum');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals(<<<Html
<select id="test-enum" class="form-control" name="Test[enum]">
<option value="">(not set)</option>
<option value="1" selected>enum1</option>
<option value="2">enum2</option>
<option value="3">enum3</option>
</select>
Html
);

            $field = FormBuilder::createActiveField($model, 'enum', [], true);

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('enum');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals([
                'class' => \maddoger\widgets\Select2::className(),
                'model' => $model,
                'attribute' => 'enum',
                'items' => [
                    Test::ENUM_1 => 'enum1',
                    Test::ENUM_2 => 'enum2',
                    Test::ENUM_3 => 'enum3',
                ],
                'clientOptions' => [
                    'allowClear' => true,
                    'closeOnSelect' => true,
                ],
                'options' => [
                    'class' => 'select2',
                    'placeholder' => '(not set)',
                    'multiple' => 'multiple',
                ],
            ]);
        });

        $this->specify('FormBuilder generates field for flags attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'flags');
        }, ['throws' => ['yii\base\InvalidConfigException', 'Flags format is not supported by netis\crud\widgets\FormBuilder']]);

        $this->specify('FormBuilder generates field for paragraphs attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'paragraphs');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('paragraphs');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals(<<<Html
<textarea id="test-paragraphs" class="form-control" name="Test[paragraphs]" rows="10" cols="80">abc
test</textarea>
Html
);
            $field = FormBuilder::createActiveField($model, 'paragraphs', [], true);

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('paragraphs');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals(<<<Html
<input type="text" id="test-paragraphs" class="form-control" name="Test[paragraphs]" value="abc
test">
Html
            );
        });

        $this->specify('FormBuilder generates field for file attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'file');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('file');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="hidden" name="Test[file]" value=""><input type="file" id="test-file" name="Test[file]">');
        });

        $this->specify('FormBuilder generates field for other attribute', function () use ($model) {
            $field = FormBuilder::createActiveField($model, 'other');

            expect($field)->isInstanceOf(ActiveField::className());
            expect($field->attribute)->equals('other');
            expect($field->parts)->hasKey('{input}');
            expect($field->parts['{input}'])->equals('<input type="text" id="test-other" class="form-control" name="Test[other]" value="test">');
        });
    }

    public function testHasRequiredField()
    {
        $model = new Test();

        $this->specify('FormBuilder hasRequiredFields method', function () use ($model) {
            $fields = FormBuilder::getFormFields($model, ['name', 'required_field']);
            expect('Model has required fields', FormBuilder::hasRequiredFields($model, $fields))->true();

            $fields = FormBuilder::getFormFields($model, ['name']);
            expect('Model has no required fields', FormBuilder::hasRequiredFields($model, $fields))->false();
        });
    }
}