<?php
require_once ("Weixin.php");
require_once ('./weixinContent.php');
$str = $_POST['str'];
$url = $_POST['url'];//先获取到两个POST变量


$weixin = new Weixin();
$weixin->log("getMsgJson");
//先针对url参数进行操作
parse_str(parse_url(htmlspecialchars_decode(urldecode($url)),PHP_URL_QUERY ),$query);//解析url地址
$biz = $query['__biz'];//得到公众号的biz

//接下来进行以下操作
//从数据库中查询biz是否已经存在，如果不存在则插入，这代表着我们新添加了一个采集目标公众号。
$weixin->exitBiz($biz);

//再解析str变量
$json = json_decode($str,true);//首先进行json_decode

//var_dump($json);exit();
$weixin->log(4);
if(!$json) {

    $json = json_decode(htmlspecialchars_decode($str), true);//如果不成功，就增加一步htmlspecialchars_decode
}

    //$json 不为空才执行, 注入操作
    foreach ($json['list'] as $k => $v) {
        $type = $v['comm_msg_info']['type'];
        if ($type == 49) {//type=49代表是图文消息
            $weixin->log(49);
           
            $is_multi = $v['app_msg_ext_info']['is_multi'];//是否是多图文消息
            $datetime = $v['comm_msg_info']['datetime'];//图文消息发送时间

                //在这里将图文消息链接地址插入到采集队列库中（队列库将在后文介绍，主要目的是建立一个批量采集队列，另一个程序将根据队列安排下一个采集的公众号或者文章内容）
            
            $id = $v['comm_msg_info']['id'];
            //todo 遍历 id-10 插入队列
            if($is_multi == 0){
                unset($content_url);
                $content_url = str_replace("\\", "", htmlspecialchars_decode($v['app_msg_ext_info']['content_url']));//获得图文消息的链接地址
                $weixin->addQueue($content_url);

                //在这里根据$content_url从数据库中判断一下是否重复
                if (!$weixin->exitContentUrl($content_url)) {

                    $fileid = $v['app_msg_ext_info']['fileid'];//一个微信给的id
                    $title = $v['app_msg_ext_info']['title'];//文章标题
                    $title_encode = urlencode(str_replace("&nbsp;", "", $title));//建议将标题进行编码，这样就可以存储emoji特殊符号了
                    $digest = $v['app_msg_ext_info']['digest'];//文章摘要
                    $source_url = str_replace("\\", "", htmlspecialchars_decode($v['app_msg_ext_info']['source_url']));//阅读原文的链接
                    $cover = str_replace("\\", "", htmlspecialchars_decode($v['app_msg_ext_info']['cover']));//封面图片

                    $is_top = 1;//标记一下是头条内容
                    //现在存入数据库
                    unset($data);
                    $data['biz'] = $biz;
                    $data['field_id'] = $fileid;
                    $data['title'] = $title;
                    $data['title_encode'] = $title_encode;
                    $data['digest'] = $digest;
                    $data['source_url'] = $source_url;
                    $data['cover'] = $cover;
                    $data['content_url'] = $content_url;
                    $data['content'] = get_content($content_url);
                    //$data['is_multi'] = $is_multi;
                    $data['datetime'] = intval($datetime);
                    $data['is_multi'] = $is_multi;
                    $lastId = $weixin->addPost($data);


                    echo "头条标题：" . $title . $lastId . "\n";//这个echo可以显示在anyproxy的终端里
                }
            } else {//如果是多图文消息
                $weixin->log(1);
                unset($data);
                foreach ($v['app_msg_ext_info']['multi_app_msg_item_list'] as $kk => $vv) {//循环后面的图文消息
                    unset($content_url);
                    $content_url = str_replace("\\", "", htmlspecialchars_decode($vv['content_url']));//图文消息链接地址
                     $weixin->addQueue($content_url);
                    //这里再次根据$content_url判断一下数据库中是否重复以免出错
                    if (!$weixin->exitContentUrl($content_url)) {
                        //在这里将图文消息链接地址插入到采集队列库中（队列库将在后文介绍，主要目的是建立一个批量采集队列，另一个程序将根据队列安排下一个采集的公众号或者文章内容）
                        $title = $vv['title'];//文章标题
                        $fileid = $vv['fileid'];//一个微信给的id
                        $title_encode = urlencode(str_replace("&nbsp;", "", $title));//建议将标题进行编码，这样就可以存储emoji特殊符号了
                        $digest = htmlspecialchars($vv['digest']);//文章摘要
                        $source_url = str_replace("\\", "", htmlspecialchars_decode($vv['source_url']));//阅读原文的链接
                        //$cover = getCover(str_replace("\\","",htmlspecialchars_decode($vv['cover'])));
                        $cover = str_replace("\\", "", htmlspecialchars_decode($vv['cover']));//封面图片
                        //现在存入数据库
                        $data['biz'] = $biz;
                        $data['title'] = $title;
                        $data['field_id'] = $fileid;
                        $data['title_encode'] = $title_encode;
                        $data['digest'] = $digest;
                        $data['source_url'] = $source_url;
                        $data['cover'] = $cover;
                        $data['content_url'] = $content_url;
                        $data['content'] = get_content($content_url);
                        $data['is_multi'] = $is_multi;
                        $lastId = $weixin->addPost($data);
                        echo "标题：" . $title . $lastId . "\n";
                    }

                }
            }
        }
    }

?>
