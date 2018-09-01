<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * 创建钱包
     *
     * @param Request $request
     * @return void
     */
    function register(Request $request)
    {
        try {
            if (DB::table("members")->where("email", $request->input("email"))->count() == 0) {
                $member_data = $request->all();
                //获取公链 key
                $result = json_decode(shell_exec("cita-cli key create"), true);
                if (is_array($result)) {
                    $member_data = array_merge($member_data, $result);

                    //发邮件
                    $subject = "CITA 账户注册成功";
                    $content = "您的钱包地址是：" . $member_data['address'] . "\r\n";
                    $content .= "您的公钥是：" . $member_data["public"] . "\r\n";
                    $content .= "您的私钥是：" . $member_data["private"] . "\r\n";
                    $toEmail = $member_data["email"];
                    Mail::raw($content, function ($message) use ($subject, $toEmail) {
                        $message->to($toEmail)->subject($subject);
                    });

                    //注册用户
                    $member_id = DB::table("members")->insertGetId($member_data);

                    return $this->__out(
                        "钱包信息已经发送至您的邮箱，请前往钱包查看",
                        [
                            'member_id' => $member_id,
                            'public' => $member_data['address']
                        ]
                    );
                } else {
                    throw new Exception("公链调用中，请稍后再试");
                }
            } else {
                throw new Exception("邮箱地址已被注册");
            }
        } catch (\Exception $e) {
            return $this->__out($e->getMessage(), [], 404);
        }
    }

    function update(Int $id, Request $request)
    {

    }

    /**
     * 会员排名
     *
     * @return void
     */
    function rank()
    {
        $members = DB::table("members")->select([
            'name',
            'token',
            'email',
            'address'
        ])->paginate(20);
        return $this->__out('ok', $members, 200);
    }

    /**
     * 查看详细
     *
     * @param Request $request
     * @return void
     */
    function show(Request $request)
    {
        $name = $request->input('name');
        try {
            if (!is_null($name)) {
                //检测邮箱地址，正则
                //if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    if ($member = DB::table("members")->where("name", $name)->first()) {

                        //查看 token
                        $result = json_decode(shell_exec('curl -X POST --data \'{"jsonrpc":"2.0","method":"getBalance", "params":["0x9480ac572b16f94a66758f110e5f10eaa42f621b", "latest"],"id":2}\' http://121.196.200.225:1337'), true);
                        //$result = null;
                        if (is_array($result)) {
                            $token = hexdec($result['result']);
                            //$token = 10;
                            if ($token > 0) {
                                DB::table("members")->where("name", $name)->update([
                                    'token' => $token,
                                ]);
                                $member->token = $token;
                            }
                            return $this->__out('ok', $member, 200);
                        } else {
                            throw new Exception("公链调用中，请稍后再试");
                        }
                    } else {
                        throw new Exception("查询不到对应的用户,请检查用户名是否正确");
                    }
                //} else {
                    //throw new Exception("参数不合法，参数必须为一个合法的邮箱地址");
                //}
            } else {
                throw new Exception("参数不合法，参数必须为微信昵称");
            }
        } catch (\Exception $e) {
            return $this->__out($e->getMessage(), [], 404);
        }
    }

    /**
     * 接收群数据
     *
     * @param Request $request
     * @return void
     */
    function message(Request $request)
    {
        try {
            //var_dump($request->all());exit;
            $incoming = json_encode($request->all());
            //$incoming = bin2hex($incoming);

            $message_data = $request->all();
            $message_data['code'] = bin2hex($incoming);

            if ($member = DB::table("members")->where("name", $message_data["name"])->get()->first()) {
                //return $this->__out('ok', ['hash' => $result['result']['hash']], 404);
            } else {
                throw new Exception("该钱包用户不存在");
            }

            //通过判断聊天内容中的关键字命中绿来发放 token
            if ($request->input("group_name") == "NervAct_hackathon_team") {
                //计算 token
                $message_data['qun_name'] = $request->input("group_name");
                $message_data['address'] = '';
                $message_data['mark'] = '+';
                if ($request->input('type') == 'text') {
                    $message_data['token'] = $this->__trigger_keyword($request->content);
                    $message_data['title'] = '按群聊贡献分配token';
                }

                //var_dump($message_data);exit;
                $hextoken = dechex($message_data['token']);

                //发起交易
                $result = json_decode(shell_exec("cita-cli rpc sendRawTransaction --private-key 0x5f0258a4778057a8a7d97809bd209055b2fbafa654ce7d31ec7191066b9225e6 --code 0x" . $message_data['code'] . " --address " . $member->address . " --value 0x0" . $hextoken . " --url http://121.196.200.225:1337"), true);
                if (is_array($result)) {
                    if ($result['result']['status'] == 'OK') {
                        DB::table("members")->where("address", $member->address)->update([
                            'token' => $member->token + $message_data['token'],
                        ]);

                        //记录聊天内容
                        $message_data['address'] = $member->address;

                        unset($message_data['group_name']);
                        DB::table("messages")->insertGetId($message_data);

                        return $this->__out('ok', ['hash' => $result['result']['hash'], 'token' => $message_data['token']], 200);
                    } else {
                    //交易失败
                        throw new Exception("交易失败");
                    }
                } else {
                    throw new Exception(json_encode($result));
                }
            }
        } catch (\Exception $e) {
            return $this->__out($e->getMessage(), [], 404);
        }
    }

    /**
     * 编码数据还原
     *
     * @param Request $request
     * @return void
     */
    function code_restore(Request $request)
    {
        $raw_data = hex2bin($request->input("code"));
        var_dump((array)json_decode($raw_data));
        exit;
    }

    /**
     * 发起交易，跳转方法
     *
     * @param String $type
     * @param Request $request
     * @return void
     */
    function transaction(String $type, Request $request)
    {
        try {
            $method = "__{$type}";
            return $this->{$method}($request);
        } catch (\Exception $e) {
            return $this->__out($e->getMessage(), [], 404);
        }
    }

    /**
     * 账户动态
     *
     * @return void
     */
    function trends()
    {
        $trends = DB::table("messages")->select(['type', 'time', 'qun_name', 'title', 'mark', 'token', 'remark'])->get();
        $output = [];
        foreach ($trends as $_trend) {
            $_output = (array)$_trend;
            switch ($_output['mark']) {
                case '+':
                    $_output['mark_lang'] = '收入';
                    break;
                case '-':
                    $_output['mark_lang'] = '支出';
                    break;
            }

            switch ($_output['type']) {
                case 'text':
                    $_output['type_lang'] = '文字';
                    break;
                case 'image':
                    $_output['type_lang'] = '图片';
                    break;
            }

            $output[] = $_output;
        }

        return $this->__out('ok', $trends);
    }

    /**
     * 群排行
     *
     * @param Request $request
     * @return void
     */
    function group_rank(Request $request)
    {
        try {
            $name = $request->input('name', null);
            if (!is_null($name)) {
                $member_count = 0;
                $token_total = 0;
                $token_ave = 0;
                $membe_rank = 1;
                $members = DB::table("members")->orderBy('token', 'asc')->get();
                foreach ($members as $_member) {
                    ++$member_count;
                    //$membe_rank ++;
                    $token_total += $_member->token;
                    if ($_member->name == $name) {
                        $membe_rank = $member_count;
                    }
                }

                $result['group_name'] = 'NervAct_hackathon_team';
                $result['token_total'] = $token_total;
                $result['token_ave'] = ceil($token_total / $member_count);
                $result['member_rank'] = $membe_rank;

                return $this->__out('ok', $result);
            } else {
                throw new Exceptio("昵称不能为空");
            }
        } catch (\Exception $e) {
            return $this->__out($e->getMessage(), [], 404);
        }
    }

    /**
     * 群内排行
     *
     * @return void
     */
    function inner_rank()
    {
        $members = [];
        $raw_members = DB::table("members")->orderBy('token', 'desc')->get();
        foreach ($raw_members as $_member) {
            $image_count = DB::table("messages")->where('name', $_member->name)->where("type", "image")->count();
            $text_count = DB::table("messages")->where('name', $_member->name)->where("type", "text")->count();
            $members[] = [
                'name' => $_member->name,
                'image_count' => $image_count,
                'text_count' => $text_count,
                'token' => $_member->token,
            ];
        }

        return $this->__out('ok', $members);
    }

    /**
     * 好友正在使用的合约
     *
     * @return void
     */
    function friends()
    {
        $friends = [
            [
                'name' => '按群聊贡献分配token',
                'friends' => [
                    'avatar_1' => '',
                    'avatar_2' => '',
                    'avatar_3' => '',
                ],
                'total' => 3,
            ],
            [
                'name' => '刺激战场游戏奖励分配token',
                'friends' => [
                    'avatar_1' => '',
                    'avatar_2' => '',
                ],
                'total' => 2,
            ]
        ];
        return $this->__out('ok', $friends);
    }

    /**
     * 合约列表
     *
     * @return void
     */
    function contract_list()
    {
        $raw_contracts = DB::table('contracts')->get();
        $contracts = [];
        foreach ($raw_contracts as &$_contract) {
            $contracts[] = (array)$_contract;
        }

        return $this->__out('ok', $contracts, 200);
    }

    /**
     * 添加合约
     *
     * @param Request $request
     * @return void
     */
    function contract_store(Request $request)
    {
        try {
            $contract_data = $request->input();
            DB::table("contracts")->insertGetId($contract_data);
            return $this->__out('ok', [], 200);
        } catch (Exception $e) {
            return $this->__out($e->getMessage(), [], 404);
        }
    }

    /**
     * 主动付款
     *
     * @param Request $request
     * @return void
     */
    private function __pay(Request $request)
    {
        $pay_data = $request->all();
        $incoming = json_encode($pay_data);
        $incoming = bin2hex($incoming);
        $admin_address = '0x4b5ae4567ad5d9fb92bc9afd6a657e6fa13a2523';


        $hextoken = dechex($pay_data['token']);

        if ($member = DB::table("members")->where("name", $pay_data["name"])->get()->first()) {
                //return $this->__out('ok', ['hash' => $result['result']['hash']], 404);
        } else {
            throw new Exception("该钱包用户不存在");
        }

        $result = json_decode(shell_exec("cita-cli rpc sendRawTransaction --private-key " . $member->private . " --code 0x" . $incoming . " --address " . $admin_address . " --value 0x0" . $hextoken . " --url http://121.196.200.225:1337"), true);
        if (is_array($result)) {
            if ($result['result']['status'] == 'OK') {
                if ($member->token - $pay_data['token'] > 0) {
                    $token = $member->token - $pay_data['token'];
                } else {
                    $token = 0;
                }
                DB::table("members")->where("address", $member->address)->update([
                    'token' => $token,
                ]);

                $pay_data['qun_name'] = 'NervAct_hackathon_team';
                $pay_data['address'] = $member->address;
                $pay_data['mark'] = '-';
                $pay_data['time'] = date("Y-m-d H:i:s", time());
                $pay_data['token'] = $request->input('token');
                $pay_data['title'] = '游戏奖励分配token';
                $pay_data['remark'] = '吃鸡押金';
                $pay_data['code'] = $incoming;

                //记录聊天内容
                unset($pay_data['group_name']);
                DB::table("messages")->insertGetId($pay_data);


                return $this->__out('ok', ['hash' => $result['result']['hash']], 200);
            } else {
                    //交易失败
                throw new Exception("交易失败");
            }
        } else {
            throw new Exception(json_encode($result));
        }

    }

    /**
     * 主动收款
     *
     * @param Request $request
     * @return void
     */
    private function __receive(Request $request)
    {
        $pay_data = $request->all();
        $pay_data['token'] = 100;
        $incoming = json_encode($pay_data);
        $incoming = bin2hex($incoming);


        $hextoken = dechex($pay_data['token']);

        if ($member = DB::table("members")->where("name", $pay_data["name"])->get()->first()) {
                //return $this->__out('ok', ['hash' => $result['result']['hash']], 404);
        } else {
            throw new Exception("该钱包用户不存在");
        }

        $result = json_decode(shell_exec("cita-cli rpc sendRawTransaction --private-key 0x5f0258a4778057a8a7d97809bd209055b2fbafa654ce7d31ec7191066b9225e6 --code 0x" . $incoming . " --address " . $member->address . " --value 0x0" . $hextoken . " --url http://121.196.200.225:1337"), true);
        if (is_array($result)) {
            if ($result['result']['status'] == 'OK') {
                DB::table("members")->where("address", $member->address)->update([
                    'token' => $member->token + $pay_data['token'],
                ]);

                $pay_data['qun_name'] = 'NervAct_hackathon_team';
                $pay_data['address'] = $member->address;
                $pay_data['mark'] = '+';
                $pay_data['time'] = date("Y-m-d H:i:s", time());
                $pay_data['token'] = 100;
                $pay_data['title'] = $request->input('app') . '游戏奖励分配token';
                $pay_data['remark'] = '太吉大利，今晚吃鸡';
                $pay_data['code'] = $incoming;

                //记录聊天内容
                unset($pay_data['group_name']);
                unset($pay_data['app']);
                DB::table("messages")->insertGetId($pay_data);

                return $this->__out('ok', ['hash' => $result['result']['hash']], 200);
            } else {
                    //交易失败
                throw new Exception("交易失败");
            }
        } else {
            throw new Exception(json_encode($result));
        }
    }


    /**
     * 交易记录
     *
     * @param Array $transaction_data
     * @return void
     */
    private function __transaction(array $transaction_data)
    {

    }

    /**
     * 格式化输出
     *
     * @param string $msg 异常消息，无异常则为 ok
     * @param mixed $data 返回数据，无数据返回则为 null
     * @param integer $code http code
     * @return void
     */
    private function __out($msg = 'ok', $data = null, $code = 200)
    {
        return response()->json([
            'msg' => $msg,
            'data' => $data,
            'code' => $code,
        ], 200);
    }

    /**
     * 用以计算命中的关键字次数
     *
     * @param String $content
     * @return Int
     */
    private function __trigger_keyword($content)
    {
        $token = 0;

        $_keywords = [
            '区块链',
            '共识机制',
            '去中心化',
            'CITA',
            '存在性证明',
            '经济模型',
            '智能合约',
            '侧链',
            '雷电网络',
            '隔离验证',
            '零知识证明',
            '稳定货币',
            'Dapp',
            'DAG',
            'Nervos',
            'PBFT',
        ];

        foreach ($_keywords as $_keyword) {
            if (strpos("---" . $content, $_keyword) > 0) {
                $token++;
            }
        }

        return $token;
    }
}
