<?php

Yii::setAlias('@tests', dirname(dirname(__DIR__)));

$db = require(__DIR__ . '/db.php');

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        'gii',
        function () {
            /** @var \netis\crud\web\Formatter $formatter */
            $formatter = Yii::$app->formatter;
            $formatter->getEnums()->set('testEnumType', [
                \app\models\Test::ENUM_1       => 'enum1',
                \app\models\Test::ENUM_2       => 'enum2',
                \app\models\Test::ENUM_3       => 'enum3',
            ]);

        }
    ],
    'controllerNamespace' => 'app\commands',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'formatter' => [
            'class'      => 'netis\crud\web\Formatter',
            'dateFormat' => 'dd-MM-yyyy',
            'datetimeFormat' => 'dd-MM-yyyy HH:mm:ss',
            'nullDisplay' => '',
            'currencyFormat' => '{value}&nbsp;{currency}',
            'thousandSeparator' => ' ',
        ],
    ],
    'modules' => [
        'gii' => [
            'class' => 'yii\gii\Module',
            'generators' => [
                'netisModel' => [
                    'class' => 'netis\crud\generators\model\Generator',
                ]
            ],
        ]
    ],
    'params' => [],
];

return $config;
