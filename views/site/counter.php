<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model app\models\SysBankcard */

$this->title = '收银台';

$res = isset($params['msg']) && $params['msg'] == 1 ? 1 : 0;

?>

<style>
    /*.navbar-header{
        background: #829fff;
    }*/

    #w0{
        display:none;
    }

    .main_area p{
        height:30px;
        line-height: 30px;
    }
</style>

<div class="counter_contain" style="font-size:15px;margin-top:-30px;">
    <div class="main_area" style="width:100%; background:lightcoral; padding:10px;color:#fff">
        <p class="amount">金额 ： <?php echo $params['amount'];?> 元 (转账金额务必与此金额一致)</p>
        <p class="owner">
            姓名 ： <span class="name_text"><?php echo $params['owner'];?></span>
            <button class="copy_name" style="background:lightcoral;float:right;border: 1px solid #fff;">复制姓名</button>
        </p>
        <p class="card_num">
            账号 ： <span class="bankcard_num_text"><?php echo $params['bankcard_num'];?></span>
            <button class="copy_bankcard_num" style="background:lightcoral;float:right;border: 1px solid #fff; ">复制账号</button>
        </p>
        <p class="bank_name">银行 ： <?php echo $params['bank_name'];?></p>
        <p class="address">地址 ： <?php echo $params['address'];?></p>
        <!--<p class="msg">msg ： <?php /*echo $params['msg'];*/?></p>-->

        <p class="tips" style="color:yellow">(支持网银、手机银行、跨行转账)</p>
    </div>

    <div class="important-tips" style="margin:10px 0 10px 0">
        <p class="tip-title" style="font-weight: bold">重要提醒</p>
        <p class="tip-1">1.转账金额请与订单金额一致</p>
        <p class="tip-2">2.姓名、账号信息必须正确填写</p>
        <p class="tip-3">3.下次存款请重新获取账号, 切勿保存旧账号再次使用</p>
        <p class="tip-4">4.订单有效期为30分钟</>
    </div>

    <div class="count_down" style="width:100%; margin:10px 0 0 40%">
        <p>支付倒计时</p>
        <p class="c_time" style="font-size:20px; font-weight: bold ; color:#829fff"><span class="c_min"></span> : <span class="c_sec"></span></p>
    </div>
</div>


<div class="error" style="display:none; font-weight:bold; color:orange; font-size: 20px; margin:0 auto">
    <?php echo isset($params['msg']) ? $params['msg'] : '发生错误, 请重新提单'; ?>
</div>

<script type="text/javascript" src="/js/jquery.js"></script>
<script type="text/javascript" src="/js/clipboard.min.js"></script>
<script type="text/javascript">
    var res = '<?php echo $res; ?>';

    if(res == 1){
        var count_down_time = '<?php echo $params['count_down_time'];?>';
        //console.log(count_down_time);
        var seconds = !isNaN(count_down_time) && count_down_time > 0 ? count_down_time : 1800;

        setInterval(function(){

            seconds--;
            if(seconds <= 0){
                $('.counter_contain').hide();
                $('.error').text('订单已过时');
                $('.error').show();


            }
            var min = Math.floor(seconds/60);
            min = min > 9 ? min : '0'+min;
            var second = seconds - (min*60);
            second = second > 9 ? second : '0'+second;

            $('.c_min').text(min);
            $('.c_sec').text(second);

        }, 1000);
    }else{
        $('.counter_contain').hide();
        $('.error').show();
    }


    var clipboard = new ClipboardJS('.copy_name', {
         text: function () {
            return $('.name_text').text();
         }
     });

    var clipboard1 = new ClipboardJS('.copy_bankcard_num', {
        text: function () {
            return $('.bankcard_num_text').text();
        }
    });

     clipboard.on('success', function (e) {
        alert("复制成功");
     });

    clipboard1.on('success', function (e) {
        alert("复制成功");
    });

     clipboard.on('error', function (e) {
        console.log('点击复制失败，请长按复制');
     });



</script>

