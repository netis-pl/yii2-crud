<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'sqlite:'.dirname(__DIR__).'/runtime/db.sql',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
];
