<?php

namespace App\Services;

use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\Event\LeaveEvent;
use LINE\LINEBot\Event\UnfollowEvent;
use LINE\LINEBot\Event\UnknownEvent;
use LINE\LINEBot\Event\MessageEvent\ImageMessage;
use LINE\LINEBot\Event\MessageEvent\StickerMessage;
use LINE\LINEBot\Event\MessageEvent\LocationMessage;
use LINE\LINEBot\Event\MessageEvent\AudioMessage;
use LINE\LINEBot\Event\MessageEvent\VideoMessage;
use LINE\LINEBot\Event\MessageEvent\UnknownMessage;
use LINE\LINEBot\Event\MessageEvent\BaseEvent;
use LINE\LINEBot\Event\PostbackEvent;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;
use Log;
use App\Services\CrawlerService;
use SteelyWing\Chinese\Chinese;

class LineBotService
{
    //
    public $starArray = [
        '水瓶' => 10,
        '雙魚' => 11,
        '牡羊' => 0,
        '金牛' => 1,
        '雙子' => 2,
        '巨蟹' => 3,
        '獅子' => 4,
        '處女' => 5,
        '天秤' => 6,
        '天蠍' => 7,
        '射手' => 8,
        '摩羯' => 9
    ];
    public function __construct(CrawlerService $crawlerService, Chinese $chinese)
    {
        $this->client = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(config('line.line_token'));
        $this->bot = new \LINE\LINEBot($this->client, ['channelSecret' => config('line.line_secret')]);
        $this->crawlerService = $crawlerService;
        $this->chinese = $chinese;
    }
    // Check request with signature and parse request
    public function checkRequest($request, $signature)
    {
        $log = [
            'request' => json_encode($request->getContent()),
            'signature' => json_encode($signature)
        ];
        try {
            $events = $this->bot->parseEventRequest($request->getContent(), $signature);
        } catch (InvalidSignatureException $e) {
            // throw new \Exception('Invalid signature');
            $log['errorMessage'] = 'Invalid signature';
            Log::info($log);
            return false;
        } catch (InvalidEventRequestException $e) {
            // throw new \Exception("Invalid event request");
            $log['errorMessage'] = 'Invalid event request';
            Log::info($log);
            return false;
        }
        $log['events'] = $events;
        Log::info($log);

        return $events;
    }
    //事件判斷並執行
    public function checkEvent($events)
    {
        foreach ($events as $event) {
            $log = [
                'event' => json_encode($event)
            ];
            //Message Event
            if ($event instanceof MessageEvent) {
                if ($event instanceof TextMessage) {
                    $log['use'] = 'text';
                    //文字
                    $replyText = $event->getText();
                    $replyToken = $event->getReplyToken();
                    $message = $this->checkText($replyText);
                    $log['getText'] = $replyText;
                    $log['getToken'] = $replyToken;
                    $log['message'] = $message;
                    if ($message) {
                        if ($replyText === "抽") {
                            $this->bot->replyMessage($replyToken, new ImageMessageBuilder($message, $message));
                        } else {
                            $this->replyText($replyToken, $message);
                        }
                    }
                } elseif ($event instanceof ImageMessage) {
                    //圖片
                } elseif ($event instanceof LocationMessage) {
                    //座標
                    $replyToken = $event->getReplyToken();
                    $latitude = $event->getLatitude(); //緯度
                    $longitude = $event->getLongitude();//經度
                    $message = $this->getWeather($latitude, $longitude);
                    $this->replyText($replyToken, $message);
                } elseif ($event instanceof AudioMessage) {
                    //聲音
                } elseif ($event instanceof VideoMessage) {
                    //影片
                } elseif ($event instanceof StickerMessage ) {
                    //貼圖
                }
            } elseif ($event instanceof UnfollowEvent) {
                //取消追蹤
            } elseif ($event instanceof FollowEvent) {
                //追蹤
            } elseif ($event instanceof JoinEvent) {
                //加入群組
            } elseif ($event instanceof LeaveEvent) {
                //離開群組
            } elseif ($event instanceof PostbackEvent) {
                //?
            } elseif ($event instanceof BeaconDetectionEvent) {
                //?
            } elseif ($event instanceof UnknownEvent) {
                //未知事件
                continue;
            } else {
                //例外不管
                continue;
            }
        }

        Log:info('line bot response:'.json_encode($log));

        return true;
    }

    public function checkText($text)
    {
        if (substr($text, 0, 5) === "!help") {
            return $this->getHelp();
        } elseif (array_key_exists($text, $this->starArray)) {
            $star = $this->starArray[$text];
            return $this->getStar($star);
        } elseif ($text === "新聞") {
            return $this->getNews();
        } elseif ($text === "匯率") {
            return $this->getRate();
        } elseif ($text === "抽") {
            return $this->getBeauty();
        } elseif ($text === "經典語錄") {
            return $this->getWord();
        } elseif ($text === "骰") {
            return $this->getRow();
        } elseif (substr($text, 0, 4) === "lulu") {
            return "汪～我是LuLu";
        } else {
            return false;
        }
    }
    //help
    public function getHelp()
    {
        $str = "打上\n";
        $str.= "!help lulu幫助你\n";
        $str.= "星座     顯示今日星座運勢 ex 摩羯\n";
        $str.= "新聞     顯示最新10則新聞\n";
        $str.= "匯率     各國兌換台幣\n";
        $str.= "抽       正妹圖\n";
        $str.= "經典語錄  唯美詩句\n";
        $str.= "天氣      傳送位置\n";
        $str.= "骰       風險骰子\n";

        return $str;
    }
    //取得星座資料
    public function getStar($star)
    {
        $today = date('Y-m-d', strtotime('now'));
        $crawler = $this->crawlerService->getOriginalData(
            'http://astro.click108.com.tw/daily_6.php?iAcDay='.$today.'&iAstro='.$star);
        $target = $this->crawlerService->getStar($crawler);
        $todayLucky = $this->crawlerService->getTodayLucky($target);
        $todayContent = $this->crawlerService->getTodayContent($target);
        $todayLucky = $this->crawlerService->getTodayLucky($target);
        $todayContent = $this->clear($todayContent);
        $todayLucky = $this->clear($todayLucky);
        $result = array_merge($todayContent, $todayLucky);
        $result = array_values(array_filter($result)); //array 去除空值 ＆ 重新排key
        $text = "";
        foreach ($result as $key => $value) {
            if ($value != "") {
                if ($key == 5) { //幸運數字
                    $text.="幸運數字:".$value."\n";
                } elseif ($key == 6) { //幸運顏色
                    $text.="幸運顏色:".$value."\n";
                } elseif ($key == 7) { //開運方位
                    $text.="開運方位:".$value."\n";
                } elseif ($key == 8) { //今日吉時
                    $text.="今日吉時:".$value."\n";
                } elseif ($key == 9) { //幸運星座
                    $text.="幸運星座:".$value."\n";
                } else {
                    $text.=$value."\n";
                }
            }
        }

        return $text;
    }
    //取得最新消息from蘋果日報
    public function getNews()
    {
        $crawler = $this->crawlerService->getOriginalData('https://today.line.me/TW/pc/main/100259');
        $news = $this->crawlerService->getNews($crawler);
        $result = $news
        ->filterXPath('//li')
        ->each(function ($node){
            $title = $node->filterXPath('//a')->text();
            $url = $node->filterXPath('//a')->evaluate('string(@href)');
            $title = $this->clear($title);

            return $title[0]."\n".$url[0]."\n";
        });
        $text = date('Y-m-d',strtotime('now'))."LineToday熱門新聞:\n\n".implode("\n", array_slice($result, 0, 8));

        return $text;
    }
    //取得匯率Other->TWD
    public function getRate()
    {
        $crawler = $this->crawlerService->getOriginalData('https://rate.bot.com.tw/xrt?Lang=zh-TW');
        $target = $this->crawlerService->getRate($crawler);
        $result = $target
        ->filterXPath('//tbody')
        ->filterXPath('//tr')
        ->each(function ($node){
            $currency = $node->filterXPath('//div[contains(@class, "visible-phone print_hide")]')->text();
            $currency = $this->clear($currency);
            $rate = $node->filterXPath('//td[contains(@data-table, "本行即期賣出")]')->text();
            $rate = $this->clear($rate);
            if ($rate[0] == "-") {
                $rate[0] = $node->filterXPath('//td[contains(@data-table, "本行現金賣出")]')->text();
            }
            return $currency[0]." ： ".$rate[0]."\n";
        });

        $text = "各國兌換台幣匯率 ： \n".implode("\n", $result);

        return $text;
    }
    //取得美女圖
    public function getBeauty()
    {
        $beauty=file_get_contents('https://api.ooopn.com/image/beauty/api.php?type=json');
        $beauty=json_decode($beauty);

        return $beauty->imgurl;

    }
    //經典語錄
    public function getWord()
    {
        $word = file_get_contents('https://api.ooopn.com/yan/api.php?type=json');
        $word = json_decode($word)->hitokoto;

        return $this->chinese->to(Chinese::ZH_HANT, $word); // 轉成繁體中文
    }
    //取得天氣
    public function getWeather($latitude, $longitude)
    {
        $location = $latitude.','.$longitude;
        $weather = file_get_contents('https://api.darksky.net/forecast/de673b8db17886863b64a517c100dcc0/'.$location .'?lang=zh-tw&exclude=[hourly,minutely,daily,alerts,flags]&units=si');
        $weather = json_decode($weather);
        $temperature = $weather->currently->temperature;
        $summary = $weather->currently->summary;
        $text = "溫度：".$temperature."\n";
        $text .= "氣象：".$summary;

        return $text;
    }
    //擲骰子
    public function getRow()
    {
        $rows = [
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大吉',
            '大凶'
        ];
        shuffle($rows);
        $index = mt_rand(0, 19);
        $text = $rows[$index];
        if ($text == "大凶") {
            $text = "結果為：".$text." 無敵衰鬼就是你";
        } else {
            $text = "結果為：".$text." 運氣真好";
        }

        return $text;
    }

    public function clear($str)
    {
        $result = htmlspecialchars($str); //去除html
        $result = str_replace(array(""," ","　","\t"), "", $result); //去除空白
        $result = trim($result); //去除空白
        $result = explode("\r\n", $result);

        return $result;
    }

    public function replyText($replyToken, $message)
    {
        return $this->bot->replyText($replyToken, $message);
    }

    public function pushMessage($userId, $message)
    {
        return $this->bot->pushMessage($userId, new TextMessageBuilder($message));
    }

    public function getProfile($userId)
    {
        if ($this->bot->getProfile($userId)->isSucceeded()) {
            return json_decode($this->bot->getProfile($userId)->getRawBody());
        }
    }
}
