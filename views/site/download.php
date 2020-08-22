<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\SysBankcard */

$this->params['breadcrumbs'] = ['label' => Yii::t('app/menu', 'download')];
$this->title = \Yii::t('app/menu', 'download');

?>

<style>

    .app{
        text-align: center;
    }
    .app img{
        width:70%
    }
    .app p{
        font-weight: bold;
        font-size: 20px;
        color: #888AFF;
    }
</style>


<div class="download">
    <div class="app">
        <img src="/image/android_qr.png" />
        <p>安卓版(请扫码下载, 或 <a href="<?php echo $backend_domain . '/download/'.$android_app_filename; ?>">点此下载</a>)</p>
    </div>

    <div class="app" style="margin:50px 0 0 0">
        <!--<img src="/image/android_qr.png" />
        <p>IOS版(请扫码下载, 或 <a href="<?php /*echo $backend_domain . '/download/'.$ios_app_filename; */?>">点此下载</a>)</p>-->
        <p>IOS版敬请期待</p>
    </div>
</div>