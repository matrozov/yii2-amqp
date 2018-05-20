Amqp Extension for Yii2
============================

This extension provides the [Amqp](https://en.wikipedia.org/wiki/Advanced_Message_Queuing_Protocol) integration for the [Yii framework 2.0](http://www.yiiframework.com) with Job interface (aka yii2-queue style) and Rpc support.

For license information check the [LICENSE](LICENSE.md)-file.

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
    //...
    'bootstrap' => ['amqp'], // For console configuration
    //...
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

### Simple work with Job

Create Job class:
```php
class MyJob extends ExecutedJob {
    public $title;
    
    public function execute() {
        // Some do here
    }
}
```

Create Job object somewhere:
```php
$job = new MyJob();
$job->title = "Hello";
Yii::$app->amqp->send('text-exchange', $job);
```

Run listener:
```bash
php yii amqp/listen
```

Specify listen for timeout execution:
```bash
php yii amqp/listen -t=1000
```

Specify listen which queue should be listened:
```bash
php yii amqp/listen test-queue test-queue-2
```
If you doesn't specified queue, listener are listen all queues specified in config.

### Rpc mode

Create Request Job and Response Job class:
```php
class MyRpcRequestJob extends RpcRequestJob {
    public $title;
    
    public function execute() {
        $job = MyRpcResponseJob();
        $job->title = 'Hello ' . $this->title;
        
        return $job;
    }   
}

class MyRpcResponseJob extends RpcResponseJob {
    public $title;
}
```
As you can see, I create object MyRpcResponseJob in MyRpcRequestJob execution method and return them.

Now, create MyRpcRequestJob and send them:
```php
$job = new MyRpcRequestJob();
$job->title = "Dolly";

$res = Yii::$app->amqp->send('text-exchange', $job, 5000);

if ($res) {
    var_dump($res->title); // Hello Dolly
}
```
Oh. When we send RpcRequestJob we automatically wait response in exclusive queue specified timeout.
Method Send() return object RpcResponseJob if we got it or Null.
RpcResponseJob doesn't contain Execute method, because you got it in main thread (not listener worker).