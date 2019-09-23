<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LineBotService;

class LineBotController extends Controller
{
    //
    public function __construct(LineBotService $service)
    {
        $this->service = $service;
    }

    public function callBack(Request $request)
    {
        //阻擋非line請求
        $signature = $request->header('X-Line-Signature');
        if (empty($signature)) {
            return response('Bad Request', 400);
        }
        //取得用戶資訊 test ok
        // $test = $this->service->getProfile('U9aef7c4b64007be9979afb213af81ba8');
        //推播 test ok
        // $test = $this->service->pushMessage('U9aef7c4b64007be9979afb213af81ba8', '推播給你拉');
        //確認請求事件
        $events = $this->service->checkRequest($request, $signature);
        if ($events) {
            //事件判斷＆處理
            $response = $this->service->checkEvent($events);
        } else {
            return response('Fail', 200);
        }

        return response('OK', 200);
    }
}
