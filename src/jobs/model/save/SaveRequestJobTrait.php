<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;

/**
 * Trait SaveRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\save
 */
trait SaveRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return bool
     * @throws
     */
    public function save(Connection $connection = null)
    {
        /** @var ModelRequestJobTrait $this */
        $response = $this->sendRequest(SaveExecuteJob::class, $connection);

        if ($response === false) {
            return false;
        }

        if (is_array($response->result)) {
            /** @var ModelRequestJob $this */
            $this->setAttributes($response->result, false);
        }

        return true;
    }
}