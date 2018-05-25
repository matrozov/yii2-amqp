# Простое, асинхронное использование

Простой режим использования похож на то, как работают очереди в yii2-queue.
Создаем нужный нам класс и добавляем соответствующие интерфейсы:

* `RequestJob` - для отправки сообщений.
* `ExecuteJob` - для обработки сообщений.

Добавляем соответствующие трейты для реализации базовых фукнций.
```php
class MyJob implements RequestJob, ExecuteJob
{
    use BaseJobTrait;
    use RequestJobTrait;

    public $title;
    
    public function exchangeName()
    {
        return 'test-exchange';
    }
    
    public function execute()
    {
        // Some do here
    }
}
```

Теперь мы в любом месте можем создать экземпляр этого класса и просто вызвать ему функцию `send`.
```php
$job = new MyJob();
$job->title = "Hello";

$job->send();
```
