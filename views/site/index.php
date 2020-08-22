<?php

/* @var $this yii\web\View */

$this->title = 'My Yii Application';
?>
<div class="site-index">

    <div class="jumbotron">
        <h1>Congratulations!</h1>

        <p class="lead">You have successfully created your Yii-powered application.</p>

        <p><a class="btn btn-lg btn-success" href="http://www.yiiframework.com">Get started with Yii</a></p>
    </div>

    <div class="body-content">

        <div class="row">
            <div class="col-lg-4">
                <h2>Heading</h2>

                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore
                    et
                    dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut
                    aliquip
                    ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum
                    dolore eu
                    fugiat nulla pariatur.</p>

                <p><a class="btn btn-default" href="http://www.yiiframework.com/doc/">Yii Documentation &raquo;</a></p>
            </div>
            <div class="col-lg-4">
                <h2>Heading</h2>

                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore
                    et
                    dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut
                    aliquip
                    ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum
                    dolore eu
                    fugiat nulla pariatur.</p>

                <p><a class="btn btn-default" href="http://www.yiiframework.com/forum/">Yii Forum &raquo;</a></p>
            </div>
            <div class="col-lg-4">
                <h2>Heading</h2>

                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore
                    et
                    dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut
                    aliquip
                    ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum
                    dolore eu
                    fugiat nulla pariatur.</p>

                <p><a class="btn btn-default" href="http://www.yiiframework.com/extensions/">Yii Extensions &raquo;</a>
                </p>
            </div>
        </div>

    </div>
</div>
<script type="text/javascript">
    var intervalPulse;
    var hostName = "<?php echo Yii::$app->params['gatewayworkerHost']; ?>";
    if ('WebSocket' in window) {
        ws = new WebSocket("ws://" + hostName + ":8282");
        ws.onopen = function () {
            intervalPulse = setInterval(function () {
                ws.send('{"type":"ping"}');
            }, 50000);
        };

        ws.onmessage = function (evt) {
            console.log("接收数据：");
            console.log(evt);
            dataX=eval("("+evt.data+")");
            if (dataX.result==1) {
                $.ajax({
                    url: '/cashier/bind-cashier',
                    type: 'post',
                    data: {'username':'test001','token':'04960563b63fbce52cc6e758d8e0fe99','userIp':'127.8.8.8','client_id':dataX.data.client_id},
                    success:function (res) {
                        console.log(res);
                    },
                    error:function () {
                        console.log('失败');
                    }
                });
            }
        };

        ws.onclose = function () {
            // 关闭 websocket
            clearInterval(intervalPulse);
            alert('通讯已断开，你将无法信息推送，请刷新页面');
        };
    } else {
        // 浏览器不支持 WebSocket
        alert('您的浏览器不支持 WebSocket!请更换浏览器以便获得更好的操作体验！');
    }
</script>