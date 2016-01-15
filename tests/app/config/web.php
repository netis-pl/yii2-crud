<?php

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
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
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'sF02g4iLjd3PodgfB4oDFVotV3BDKLMB',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => require(__DIR__ . '/db.php'),
        'formatter' => [
            'class'      => 'netis\crud\web\Formatter',
            'dateFormat' => 'dd-MM-yyyy',
            'datetimeFormat' => 'dd-MM-yyyy HH:mm:ss',
            'nullDisplay' => '',
            'currencyFormat' => '{value}&nbsp;{currency}',
            'thousandSeparator' => ' ',
        ],
    ],
    'params' => [
        'adminEmail' => 'admin@example.com',
    ],
];

return $config;
