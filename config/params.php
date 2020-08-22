<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'gatewayworkerHost'=>'globalpayapi.com',
    'password' => 'password@@@@@!',
    'Login_Status_Expire_Time' => 604800,  //登录态过期时间(7*24*3600  一周)

    'merchant_id' => '10099',
    'merchant_key' => '40fefe6c2b7e4aefe053e98bd978c67a',
    'typay_deposit_url' => 'http://47.75.127.22:8081',

    //充值重定向及回调url
    'deposit_return_url' => '',
    'deposit_notify_url' => '/api/deposit-notify',

    //取款重定向及回调url
    'withdraw_return_url' => '',
    'withdraw_notify_url' => '/api/withdraw-notify',

    //收银台页面收款渠道数据加密key
    'counter_key' => '80fefg6gtb7e4aefe238fdt8',

];