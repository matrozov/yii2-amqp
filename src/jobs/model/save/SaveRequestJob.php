<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Class SaveRequestJob
 * @package matrozov\yii2amqp\jobs\model\save
 */
abstract class SaveRequestJob extends ModelRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return bool
     * @throws
     */
    public function save(Connection $connection = null)
    {
        $response = $this->sendRequest('executeSave', $connection);

        if ($response === false) {
            return false;
        }

        if (is_array($response->result)) {
            $this->setAttributes($response->result, false);
        }

        return true;
    }
}