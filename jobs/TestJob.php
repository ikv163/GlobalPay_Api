<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 21:04
 */

namespace app\jobs;

use yii\base\BaseObject;

class TestJob extends BaseObject implements \yii\queue\RetryableJobInterface
{

    public function execute($queue)
    {
        try{
            \Yii::info('我是Job任务', 'Job任务1');
            \Yii::warning('我是Job任务', 'Job任务2');
            \Yii::error('我是Job任务', 'Job任务3');
        }catch (\Exception $e){
            \Yii::error($e->getFile().'-'.$e->getMessage().'-'.$e->getLine(), 'Job任务4');
        }

    }

    /**
     * @return int time to reserve in seconds
     */
    public function getTtr()
    {
        return 2 * 60;
    }

    /**
     * @param int $attempt number
     * @param \Exception|\Throwable $error from last execute of the job
     * @return bool
     */
    public function canRetry($attempt, $error)
    {
        return ($attempt < 3);
    }

}