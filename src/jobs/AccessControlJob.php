<?php

namespace matrozov\yii2amqp\jobs;

/**
 * Interface AccessControlJob
 * @package matrozov\yii2amqp\jobs
 */
interface AccessControlJob
{
    /**
     * @return array
     *
     *  return [
     *      'permissions' => ['order_edit'],
     *      'roles' => ['admin'],
     *      'roleParams' => function (Job $job) {
     *          return [
     *              'order_id' => $job->order_id
     *          ];
     *      },
     *      'matchCallback' => function (Job $job) {
     *          ...
     *      },
     *      'denyCallback' => function (Job $job) {
     *          ...
     *      }
     *  ];
     */
    public function accessControl();
}