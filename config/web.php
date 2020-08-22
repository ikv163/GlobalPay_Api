<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', /*'qmqueue', 'queue_cashier_deposit', 'queue_cashier_withdraw'*/],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@components' => '@app/components', //add
    ],


    // 操作日志
    'on beforeRequest' => function($event) {
        \yii\base\Event::on(\yii\db\BaseActiveRecord::className(), \yii\db\BaseActiveRecord::EVENT_BEFORE_UPDATE, ['components\DbLog', 'beforeUpdate']);
        \yii\base\Event::on(\yii\db\BaseActiveRecord::className(), \yii\db\BaseActiveRecord::EVENT_AFTER_UPDATE, ['components\DbLog', 'afterUpdate']);
        \yii\base\Event::on(\yii\db\BaseActiveRecord::className(), \yii\db\BaseActiveRecord::EVENT_AFTER_DELETE, ['components\DbLog', 'afterDelete']);
        \yii\base\Event::on(\yii\db\BaseActiveRecord::className(), \yii\db\BaseActiveRecord::EVENT_AFTER_INSERT, ['components\DbLog', 'afterInsert']);
    },


    'components' => [
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => 'localhost',
            'port' => 6379,
            'database' => 0,
            'password' => '123qwe'

            /*'hostname' => '47.56.137.28',
            'port' => 6379,
            'database' => 0,
            'password' => 'yabo123!@#'*/
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'zVzr36gZpOvWVH8-9SNhR9GUnQrDwO4w',
            'enableCsrfValidation' => false,
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
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'logVars' => [],
                    'logFile' => '@runtime/logs/app.log',
                    'microtime' => true,
                ],
            ],
        ],
        'db' => $db,
        'qmqueue' => [
            'class' => \yii\queue\beanstalk\Queue::class,
            //'host' => '47.56.83.121',
            'host' => 'localhost',
            'port' => 11300,
            'tube' => 'qmqueue',
            'as log' => \yii\queue\LogBehavior::class
        ],
        /*
        'queue_cashier_deposit' => [
            'class' => \yii\queue\beanstalk\Queue::class,
            'host' => '47.56.83.121',
            'port' => 11300,
            'tube' => 'queue_cashier_deposit',
            'as log' => \yii\queue\LogBehavior::class
        ],

        'queue_cashier_withdraw' => [
            'class' => \yii\queue\beanstalk\Queue::class,
            'host' => '47.56.83.121',
            'port' => 11300,
            'tube' => 'queue_cashier_withdraw',
            'as log' => \yii\queue\LogBehavior::class
        ],*/
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],

        'i18n' => [
            'translations' => [
                'app*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@components/messages',
                    'fileMap' => [
                        'app' => 'app.php',
                        'app/menu' => 'menu.php',
                        'app/model' => 'model.php',
                        'app/ctrl' => 'ctrl.php',
                        'app/view' => 'view.php',
                        'app/error' => 'error.php',
                    ],
                ],
            ],
        ],
    ],
    'params' => $params,

    'language' => 'zh-CN',
    'timeZone' => 'Asia/Shanghai',
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];
}

return $config;
