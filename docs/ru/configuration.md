# Настройка

Для использования расширения, просто добавьте этот код в конфигурацию вашего приложения:

```php
return [
    //...
    'bootstrap' => ['amqp'], // Добавьте компонент в секцию bootstrap для консолькой конфигурации
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
