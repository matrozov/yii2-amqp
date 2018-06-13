<?php
namespace matrozov\yii2amqp\jobs\model\save;

/**
 * Interface ModelSaveExecuteJob
 * @package matrozov\yii2amqp\jobs\model\save
 */
interface ModelSaveExecuteJob
{
    public function load($data, $formName = null);
    public function validate();
    public function getErrors();

    public function executeSave();
}