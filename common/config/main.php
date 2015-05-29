<?php
return [
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [//使用memcached作缓存
            'class' => '\yii\caching\MemCache',
            'useMemcached' => true,
            'servers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 100,
                ],
            ],
        ],
        'session' => [//配置session使用上面的缓存组件服务
            'class' => '\yii\web\CacheSession',
            'cache' => 'cache',
        ],

        'sphinx' => [
            'class' => 'yii\sphinx\Connection',
            'dsn' => 'mysql:host=rdsnnamnbnnamnbprivate.mysql.rds.aliyuncs.com;port=9306',
            'username' => 'maxwelldu',
            'password' => 'yu13jiu14',
        ],
    ],
    'language' => 'zh-CN',
];
