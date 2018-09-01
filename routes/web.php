<?php

use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    //return $router->app->version();exit;
        // 发送验证码
        $subject = '随机再生成剩成一段话';
        $content = "发送于" . date("Y-m-d H:i:s", time());
	$toEmail = "250226867@qq.com";

        Mail::raw($content, function ($message) use ($subject, $toEmail) {
            //cache([$cacheName => $phrase], now()->addMinutes(self::EMAIL_TIME_TO_EXPIRED));
            $message->to($toEmail)->subject($subject);
        });
});

$router->get('/api', function(){
    echo 'asf';exit;
});

//创建
$router->post('/api/member/register', 'Controller@register');
//更新
$router->put('/api/member/put[/{email}]', 'Controller@update');
//查看
$router->get('/api/member[/{email}]', 'Controller@show');
//排名
$router->get('/api/members', 'Controller@rank');
//抓取聊天数据
$router->post('/api/message', 'Controller@message');
//发起交易
$router->post('/api/transaction[/{type}]', 'Controller@transaction');
//账户动态
$router->get('/api/members/trends', 'Controller@trends');
//群排行
$router->get('/api/group-rank', 'Controller@group_rank');
//群内排行
$router->get('/api/members/rank', 'Controller@inner_rank');
//好友正在使用的
$router->get('/api/members/friends', 'Controller@friends');
//code 还原
$router->post('/api/code/restore', 'Controller@code_restore');
//合约列表
$router->get('/api/contracts', 'Controller@contract_list');
//添加合约
$router->post('/api/contracts', 'Controller@contract_store');