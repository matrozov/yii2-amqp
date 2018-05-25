# Синхронный режим Rpc

Расширение yii2-amqp позволяет организовать синхронный режим работы с ожиданием ответа.

Для реализации отправки сообщений, создайте класс реализующий следующие интерфейсы:

* `RpcRequestJob` - для отправки rpc-сообщений.
* `RpcExecuteJob` - для обработки rpc-сообщений.

Для отправки и обработки ответных сообщений создайте еще один класс реализующий интерфейс `RpcResponseJob`.

Добавляем соответствующие трейты для реализации базовых фукнций.
```php
class MyRpcRequestJob implements RpcRequestJob, RpcExecuteJob
{
    use BaseJobTrait;
    use RpcRequestJobTrait;
    
    public $title;
    
    public function exchangeName()
    {
        return 'test-exchange';
    }
    
    public function execute()
    {
        $job = MyRpcResponseJob();
        $job->title = 'Hello ' . $this->title;
        
        return $job;
    }   
}

class MyRpcResponseJob implements RpcResponseJob
{
    use BaseJobTrait;

    public $title;
}
```

В методе `execute` класс запроса реализуем обработку данных запроса и создание класса ответа,
который и возвращаем как результат функции `execute`.

Теперь организуем саму отправку. В произвольном месте создаем наш класс запроса:
```php
$job = new MyRpcRequestJob();
$job->title = "Dolly";

$res = $job->send();

if ($res) {
    var_dump($res->title); // Hello Dolly
}

Как вы видите, мы получаем результат из функции `send`, который и будет равен нашему классу ответа.
