<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'qmqueue', 'queue_cashier_deposit', 'queue_cashier_withdraw'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
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
        'qmqueue' => [
            'class' => \yii\queue\beanstalk\Queue::class,
            'host' => '47.56.83.121',
            'port' => 11300,
            'tube' => 'qmqueue',
            'as log' => \yii\queue\LogBehavior::class
        ],

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
        ],
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
