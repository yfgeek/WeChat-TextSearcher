<?php

/*
说明：基于微信的简单信息查询平台
使用以下代码 你需要更改的4个信息
1.你的token
2.你的数据库地址，并且存放数据库文件且为txt
3.你的读取参数 #hehe
4.你的日志文件保存目录 可以是 ../../ 放到无法下载到的地方
*/
//定义你的token
define("TOKEN", "yourtoken");
$time_start = microtime(true);
define('ROOT', dirname(__FILE__) . '/');
define('MATCH_LENGTH', 0.1 * 1024); //字符串长度 0.1M 
define('RESULT_LIMIT', 5000);
function my_scandir($path) { 
    $filelist = array();
    if ($handle = opendir($path)) {
        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
                if (is_dir($path . "/" . $file)) {
                    $filelist = array_merge($filelist, my_scandir($path . "/" . $file));
                } else {
                    $filelist[] = $path . "/" . $file;
                }
            }
        }
    }
    closedir($handle);
    return $filelist;
}
//查询
function get_results($keyword) {
    $return = array();
    $count = 0;
    $datas = my_scandir(ROOT . "database"); //数据库文档目录
    if (!empty($datas)) foreach ($datas as $filepath) {
        $filename = basename($filepath);
        $start = 0;
        $fp = fopen($filepath, 'r');
        while (!feof($fp)) {
            fseek($fp, $start);
            $content = fread($fp, MATCH_LENGTH);
            $content.= (feof($fp)) ? "\n" : '';
            $content_length = strrpos($content, "\n");
            $content = substr($content, 0, $content_length);
            $start+= $content_length;
            $end_pos = 0;
            while (($end_pos = strpos($content, $keyword, $end_pos)) !== false) {
                $start_pos = strrpos($content, "\n", -$content_length + $end_pos);
                $start_pos = ($start_pos === false) ? 0 : $start_pos;
                $end_pos = strpos($content, "\n", $end_pos);
                $end_pos = ($end_pos === false) ? $content_length : $end_pos;
                $return[] = array(
                    'f' => $filename,
                    't' => trim(substr($content, $start_pos, $end_pos - $start_pos))
                );
                $count++;
                if ($count >= RESULT_LIMIT) break;
            }
            unset($content, $content_length, $start_pos, $end_pos);
            if ($count >= RESULT_LIMIT) break;
        }
        fclose($fp);
        if ($count >= RESULT_LIMIT) break;
    }
    return $return;
}
//安全起见 保存日志
function saveit($data) {
    date_default_timezone_set('Etc/GMT-8');
    $open = fopen("log.log", "a+");
    $str = date("Y-m-d H:i:s", time()) . "\t" . $_SERVER['REMOTE_ADDR'] . "\t" . $data . "\n";
    $str = $str . "\n";
    fwrite($open, $str);
    fclose($open);
}
function returnkeyword($keytxt) {
    set_time_limit(4); //不限定脚本执行时间
    $q = strip_tags(trim($keytxt));
    $results = get_results($q);
    foreach ($results as $v) {
        $txt = $txt . "从" . $v['f'] . "中获得：" . $v['t'] . " ";
    }
    saveit($txt);
    return $txt;
}
$wechatObj = new wechatCallbackapiTest();
$wechatObj->responseMsg();
class wechatCallbackapiTest {
    public function responseMsg() {
        //get post data, May be due to the different environments
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        //extract post data
        if (!empty($postStr)) {
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);
            switch ($RX_TYPE) {
                case "text":
                    $resultStr = $this->handleText($postObj);
                    break;

                case "event":
                    $resultStr = $this->handleEvent($postObj);
                    break;

                default:
                    $resultStr = "Unknow msg type: " . $RX_TYPE;
                    break;
            }
            echo $resultStr;
        } else {
            echo "";
            exit;
        }
    }
    public function handleText($postObj) {
        $fromUsername = $postObj->FromUserName;
        $toUsername = $postObj->ToUserName;
        $keyword = trim($postObj->Content);
        $time = time();
        $textTpl = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[%s]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
					<FuncFlag>0</FuncFlag>
					</xml> ";
        if (!empty($keyword)) {
            saveit($keyword);
            $msgType = "text";
            if (substr($keyword, 0, 5) == '#hehe') {
                $keyword = substr($keyword, 5);
                $contentStr = returnkeyword($keyword);
            } else {
                $contentStr = '欢迎回复特定代码获得相关信息';
            }
            $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
            echo $resultStr;
        } else {
            echo "请输入有效字符";
        }
    }
    public function handleEvent($object) {
        $contentStr = "";
        switch ($object->Event) {
            case "subscribe":
                $contentStr = "感谢您关注我";
                break;

            default:
                $contentStr = "Unknow Event: " . $object->Event;
                break;
        }
        $resultStr = $this->responseText($object, $contentStr);
        return $resultStr;
    }
    public function responseText($object, $content, $flag = 0) {
        $textTpl = "<xml>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                    <FuncFlag>%d</FuncFlag>
                    </xml>";
        $resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time() , $content, $flag);
        return $resultStr;
    }
    private function checkSignature() {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array(
            $token,
            $timestamp,
            $nonce
        );
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }
}
?>
