# Синхронный режим Rpc-Model

Расширение yii2-amqp реализует расширенный режим работы в режиме rpc с эмуляцией удаленной работы с моделями.

Данный режим позволяет реализовать удаленную валидацию и сохранение модели с результатом и обработкой ошибок
на стороне отправителя.

Для реализации отправки сообщений, создайте класс, наследник Model или любого его потомка (например ActiveRecord)
и реализующий следующие интерфейсы:

* `ModelRequestJob` - для отправки rpc-model-сообщений.
* `ModelExecuteJob` - для обработки rpc-model-сообщений.

Нужно отметить, что этот класс, на принимающей стороне, должен реализовывать дополнительный метод `save`, которого нет
в базовой модели, но, например, есть в ActiveRecord.

Добавляем соответствующие трейты для реализации базовых фукнций.
```php
class MyModelRequestJob extends ActiveRecord implements ModelRequestJob, ModelExecuteJob
{
    use BaseJobTrait;
    use ModelRequestJobTrait;
    use ModelExecuteJobTrait;
    
    public function exchangeName()
    {
        return 'test-exchange';
    }
}
```

Как вы могли заметить, вы не указываете обработку сообщений, как и сам метод `execute`, так как обработкой
по сути является валидация и сохранение этой модели.

Теперь организуем саму отправку. В произвольном месте создаем наш класс запроса:
```php
$job = new MyModelRequestJob();

if ($job->load(Yii::$app->request->post()) && $job->save()) {
    ...
}

if ($job->hasErrors) {
    var_dump($job->errors);
}
```
Мы вызываем привычный метод `save`, который в автоматическом режиме создает
rpc-запрос через очередь с указанными вами данными, обрабатывает их на принимающей стороне и результат
обработки, в виде bool-ответа функции `save` и стандартного массима ошибок для `Model` в `errors`, возвращает
обратно в нашу модель.

Такой метод позволяет не думая организовать удаленный механизм сохранения и валидации моделей на принимающей стороне.