Amqp Extension for Yii2
============================

This extension provides the [Amqp](https://en.wikipedia.org/wiki/Advanced_Message_Queuing_Protocol) integration for the [Yii framework 2.0](http://www.yiiframework.com) with Job interface (aka yii2-queue style) and Rpc support.

For license information check the [LICENSE](LICENSE.md)-file.

Documentation is at [docs/guide-ru/README.md](docs/guide-ru/README.md).

[![Latest Stable Version](https://poser.pugx.org/matrozov/yii2-amqp/v/stable.png)](https://packagist.org/packages/matrozov/yii2-amqp)
[![Total Downloads](https://poser.pugx.org/matrozov/yii2-amqp/downloads.png)](https://packagist.org/packages/matrozov/yii2-amqp)
[![License](https://poser.pugx.org/matrozov/yii2-amqp/license)](https://packagist.org/packages/matrozov/yii2-amqp)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run
```
php composer.phar require --prefer-dist matrozov/yii2-amqp
```

or add

```
"matrozov/yii2-amqp": "dev-master"
```

to the require section of your composer.json.

## Configuration

To use this extension, simply add the following code in your application configuration:

```php
return [
    //....
    'components' => [
        'amqp' => [
            'class'     => 'matrozov\yii2amqp\Connection',
            'host'      => 'queue',
            'port'      => 5672,
            'user'      => 'guest',
            'password'  => 'guest',
            'vhost'     => '/',
            'queues' => [
                [
                    'name' => 'test-queue',
                ],
                [
                    'name' => 'test-queue-2',
                ],
            ],
            'exchanges' => [
                [
                    'name' => 'test-exchange',
                ],
            ],
            'bindings' => [
                [
                    'queue' => 'test-queue',
                    'exchange' => 'test-exchange',
                ],
                [
                    'queue' => 'test-queue-2',
                    'exchange' => 'test-exchange',
                ]
            ]
        ],
    ],
];
```

## Usage