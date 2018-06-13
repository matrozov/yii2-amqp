<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;
use yii\base\ErrorException;

/**
 * Trait ModelSaveRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\save
 */
trait ModelSaveRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return bool
     * @throws ErrorException
     */
    public function save(Connection $connection = null)
    {
        /* @var ModelSaveRequestJob $this */
        if (!$this->validate()) {
            return false;
        }

        $connection = Connection::instance($connection);

        /* @var ModelSaveRequestJob $className */
        $className = static::class;

        $request = new ModelSaveInternalRequestJob();
        $request->className  = $className;
        /* @var ModelSaveRequestJob $this */
        $request->attributes = $this->toArray();

        $response = $connection->send($request, $className::exchangeName());

        if (!$response) {
            return false;
        }

        if ($response instanceof RpcFalseResponseJob) {
            return false;
        }

        if (!($response instanceof ModelSaveInternalResponseJob)) {
            throw new ErrorException('Response should be ModelSaveInternalResponseJob!');
        }

        /* @var ModelSaveInternalResponseJob $response */
        /* @var ModelSaveRequestJob $this */
        $this->addErrors($response->errors);

        if ($response->success) {
            foreach ($response->primaryKeys as $key => $value) {
                $this->$key = $value;
            }
        }

        return $response->success;
    }
}