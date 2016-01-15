<?php

use yii\db\Schema;
use yii\db\Migration;

class m160115_191316_create_test_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%test}}', [
            'id'              => Schema::TYPE_PK,
            'required_field'  => Schema::TYPE_STRING . ' NOT NULL',
            'text'            => Schema::TYPE_STRING,
            'boolean'         => Schema::TYPE_BOOLEAN,
            'length'          => Schema::TYPE_INTEGER,
            'weight'          => Schema::TYPE_INTEGER,
            'multiplied_100'  => Schema::TYPE_INTEGER,
            'multiplied_1000' => Schema::TYPE_INTEGER,
            'integer'         => Schema::TYPE_INTEGER,
            'time'            => Schema::TYPE_TIME,
            'datetime'        => Schema::TYPE_DATETIME,
            'date'            => Schema::TYPE_DATE,
            'enum'            => Schema::TYPE_INTEGER,
            'flags'           => Schema::TYPE_INTEGER,
            'paragraphs'      => Schema::TYPE_TEXT,
            'file'            => Schema::TYPE_TEXT,
            'other'           => Schema::TYPE_BINARY,
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%test}}');
    }
}
