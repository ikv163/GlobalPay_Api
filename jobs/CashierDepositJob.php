<?php

namespace app\jobs;

use yii\base\BaseObject;

class CashierDepositJob extends BaseObject implements \yii\queue\RetryableJobInterface
{
    public $orderModel;
    public function execute($queue)
    {
        return true;
    }

    /**
     * @return int time to reserve in seconds
     */
    public
    function getTtr()
    {
        return 2 * 60;
    }

    /**
     * @param int $attempt number
     * @param \Exception|\Throwable $error from last execute of the job
     * @return bool
     */
    public
    function canRetry($attempt, $error)
    {
        \Yii::error($error);
        return ($attempt < 3);
    }
}