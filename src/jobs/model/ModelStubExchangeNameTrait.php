<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\base\ErrorException;

/**
 * Trait ModelStubExchangeNameTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelStubExchangeNameTrait
{
    /**
     * @throws
     */
    public static function exchangeName()
    {
        throw new ErrorException('Method can\'t be callable!');
    }
}