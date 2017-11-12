<?php

/**
 * 字符处理
 *
 */
class Text {

    /**
     * 隐藏电话号码中的一段
     * @param type $phone
     * @return type
     */
    static public function hidtel($phone) {
        $IsWhat = preg_match('/(0[0-9]{2,3}[-]?[2-9][0-9]{6,7}[-]?[0-9]?)/i', $phone); //固定电话
        if ($IsWhat == 1) {
            return preg_replace('/(0[0-9]{2,3}[-]?[2-9])[0-9]{3,4}([0-9]{3}[-]?[0-9]?)/i', '$1****$2', $phone);
        } else {
            return preg_replace('/(1[358]{1}[0-9])[0-9]{5}([0-9]{3})/i', '$1****$2', $phone);
        }
    }

    /** +----------------------------------------------------------
     * 将一个字符串部分字符用*替代隐藏
     * +----------------------------------------------------------
     * @param string    $string   待转换的字符串
     * @param int       $bengin   起始位置，从0开始计数，当$type=4时，表示左侧保留长度
     * @param int       $len      需要转换成*的字符个数，当$type=4时，表示右侧保留长度
     * @param int       $type     转换类型：
     *                              0，从左向右隐藏；
     *                              1，从右向左隐藏；
     *                              2，从指定字符位置分割前由右向左隐藏；
     *                              3，从指定字符位置分割后由左向右隐藏；
     *                              4，保留首末指定字符串
     * @param string    $glue     分割符
     * +----------------------------------------------------------
     * @return string   处理后的字符串
     * +----------------------------------------------------------
     */
    static public function hideStr($string, $bengin = 0, $len = 4, $type = 0, $glue = "@") {
        if (empty($string)) {
            return '';
        }
        $array = [];
        if ($type == 0 || $type == 1 || $type == 4) {
            $strlen = $length = mb_strlen($string);
            if ($strlen <= $len) {  // 修正字符太短的问题
                $len = $strlen - 1;
            }
            while ($strlen) {
                $array[] = mb_substr($string, 0, 1, "utf8");
                $string = mb_substr($string, 1, $strlen, "utf8");
                $strlen = mb_strlen($string);
            }
        }
        if ($type == 0) {
            for ($i = $bengin; $i < ($bengin + $len); $i++) {
                if (isset($array[$i])) {
                    $array[$i] = '*';
                }
            }
            $string = implode('', $array);
        } else if ($type == 1) {
            $array = array_reverse($array);
            for ($i = $bengin; $i < ($bengin + $len); $i++) {
                if (isset($array[$i])) {
                    $array[$i] = '*';
                }
            }
            $string = implode('', array_reverse($array));
        } else if ($type == 2) {
            $array = explode($glue, $string);
            $array[0] = self::hideStr($array[0], $bengin, $len, 1);
            $string = implode($glue, $array);
        } else if ($type == 3) {
            $array = explode($glue, $string);
            $array[1] = self::hideStr($array[1], $bengin, $len, 0);
            $string = implode($glue, $array);
        } else if ($type == 4) {
            $left = $bengin;
            $right = $len;
            $tem = [];
            for ($i = 0; $i < ($length - $right); $i++) {
                if (isset($array[$i])) {
                    $tem[] = ($i >= $left) ? '*' : $array[$i];
                }
            }
            if ($right > 0) {
                $array = array_chunk(array_reverse($array), $right);
                $array = array_reverse($array[0]);
                for ($i = 0; $i < $right; $i++) {
                    $tem[] = $array[$i];
                }
            }
            $string = implode('', $tem);
        } return $string;
    }

    /**
     * 生成UUID 单机使用
     *
     * @access public
     * @return string
     */
    static public function uuid() {
        $charid = md5(uniqid(mt_rand(), true));
        $hyphen = chr(45);
        $uuid = chr(123) . substr($charid, 0, 8) . $hyphen . substr($charid, 8, 4) . $hyphen . substr($charid, 12, 4) . $hyphen . substr($charid, 16, 4) . $hyphen . substr($charid, 20, 12) . chr(125);
        return $uuid;
    }

    /**
     * 生成Guid主键
     *
     * @return Boolean
     */
    static public function keyGen() {
        return str_replace('-', '', substr(self::uuid(), 1, -1));
    }

    /**
     * 字符串截取，支持中文和其他编码
     * @static
     * @access public
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $charset 编码格式
     * @param string $suffix 截断显示字符
     * @return string
     */
    static public function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
        if (empty($str)) {
            return "";
        }
        if (function_exists("mb_substr")) {
            if ($length >= mb_strlen($str)) {
                return $str;
            }
            $slice = mb_substr($str, $start, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $charset);
        } else {
            $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
        }
        return $suffix ? $slice . '...' : $slice;
    }

    /**
     * 产生随机字串，可用来自动生成密码 默认长度6位 字母和数字混合 支持中文
     *
     * @param string $len 长度
     * @param string $type 字串类型 0 字母 1 数字 其它 混合
     * @param string $addChars 额外字符
     * @return string
     */
    static public function randString($len = 6, $type = '', $addChars = '') {
        $str = '';
        switch ($type) {
            case 0 :
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' . $addChars;
                break;
            case 1 :
                $chars = str_repeat('0123456789', 3);
                break;
            case 2 :
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . $addChars;
                break;
            case 3 :
                $chars = 'abcdefghijklmnopqrstuvwxyz' . $addChars;
                break;
            case 4 :
                $chars = "们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借" . $addChars;
                break;
            default :
                // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
                $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789' . $addChars;
                break;
        }
        if ($len > 10) { // 位数过长重复字符串一定次数
            $chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
        }
        if ($type != 4) {
            $chars = str_shuffle($chars);
            $str = substr($chars, 0, $len);
        } else {
            // 中文随机字
            for ($i = 0; $i < $len; $i++) {
                $str .= self::msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1, 'utf-8', false);
            }
        }
        return $str;
    }

    /**
     * 生成一定数量的随机数，并且不重复
     *
     * @param integer $number 数量
     * @param string $len 长度
     * @param string $type 字串类型 0 字母 1 数字 其它 混合
     * @return string
     */
    static public function buildCountRand($number, $length = 4, $mode = 1) {
        if ($mode == 1 && $length < strlen($number)) {
            return false;
        }
        $rand = [];
        for ($i = 0; $i < $number; $i++) {
            $rand[] = self::randString($length, $mode);
        }
        $unqiue = array_unique($rand);
        if (count($unqiue) == count($rand)) {
            return $rand;
        }
        $count = count($rand) - count($unqiue);
        for ($i = 0; $i < $count * 3; $i++) {
            $rand[] = self::randString($length, $mode);
        }
        $rand = array_slice(array_unique($rand), 0, $number);
        return $rand;
    }

    /**
     * 获取一定范围内的随机数字 位数不足补零
     *
     * @param integer $min 最小值
     * @param integer $max 最大值
     * @return string
     */
    static public function randNumber($min, $max) {
        return sprintf("%0" . strlen($max) . "d", mt_rand($min, $max));
    }

    /**
     * 带格式生成随机字符 支持批量生成 但可能存在重复
     *
     * @param string $format 字符格式 # 表示数字 * 表示字母和数字 $ 表示字母
     * @param integer $number 生成数量
     * @return string | array
     */
    static public function buildFormatRand($format, $number = 1) {
        $str = [];
        $length = strlen($format);
        for ($j = 0; $j < $number; $j++) {
            $strtemp = '';
            for ($i = 0; $i < $length; $i++) {
                $char = substr($format, $i, 1);
                switch ($char) {
                    case "*" : // 字母和数字混合
                        $strtemp .= self::randString(1);
                        break;
                    case "#" : // 数字
                        $strtemp .= self::randString(1, 1);
                        break;
                    case "$" : // 大写字母
                        $strtemp .= self::randString(1, 2);
                        break;
                    default : // 其他格式均不转换
                        $strtemp .= $char;
                        break;
                }
            }
            $str[] = $strtemp;
        }
        return $number == 1 ? $strtemp : $str;
    }

    // 自动转换字符集 支持数组转换
    static public function autoCharset($string, $from = 'gbk', $to = 'utf-8') {
        $from = strtoupper($from) == 'UTF8' ? 'utf-8' : $from;
        $to = strtoupper($to) == 'UTF8' ? 'utf-8' : $to;
        if (strtoupper($from) === strtoupper($to) || empty($string) || (is_scalar($string) && !is_string($string))) {
            //如果编码相同或者非字符串标量则不转换
            return $string;
        }
        if (is_string($string)) {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($string, $to, $from);
            } elseif (function_exists('iconv')) {
                return iconv($from, $to, $string);
            } else {
                return $string;
            }
        } elseif (is_array($string)) {
            foreach ($string as $key => $val) {
                $_key = self::autoCharset($key, $from, $to);
                $string[$_key] = self::autoCharset($val, $from, $to);
                if ($key != $_key)
                    unset($string[$key]);
            }
            return $string;
        }
        else {
            return $string;
        }
    }

    /**
     * 查询字符是否存在于某字符串
     *
     * @param $haystack 在该字符串中进行查找
     * @param $needle 要查找的字符
     * @return bool
     */
    static public function str_exists($haystack, $needle) {
        return !(strpos($haystack, $needle) === FALSE);
    }

    /**
     * 去掉字符串前后的空格(半全角空格)
     *
     * @param string $str
     * return 处理后的文本
     */
    static public function trim_cn($str) {
        $str = ' ' . $str;
        return preg_replace('/(^[\s\x{3000}]*)|([\s\x{3000}]*$)/u', '', $str);
    }

    /**
     *  将一个字串中含有全角的数字字符、字母、空格或'%+-()'字符转换为相应半角字符
     *
     * @access  public
     * @param   string       $str         待转换字串
     *
     * @return  string       $str         处理后字串
     */
    static public function make_semiangle($str) {
        $arr = ['０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
            'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
            'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
            'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
            'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
            'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z',
            '（' => '(', '）' => ')', '［' => '[', '］' => ']', '【' => '[',
            '】' => ']', '〖' => '[', '〗' => ']', '「' => '[', '」' => ']',
            '『' => '[', '』' => ']', '｛' => '{', '｝' => '}', '《' => '<',
            '》' => '>',
            '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
            '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
            '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
            '＂' => '"', '＇' => '`', '｀' => '`', '｜' => '|', '〃' => '"',
            '　' => ' '];

        return strtr($str, $arr);
    }

    /**
     * 过滤掉Emoji表情字符
     * @param type $string
     * @return type
     */
    public static function emojistrip($string) {
        return preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', $string);
    }

    /**
     * Emoji原形转换为String
     * @param string $content
     * @return string
     */
    public static function emojiEncode($content) {
        return json_decode(preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function($str) {
                    return addslashes($str[0]);
                }, json_encode($content)));
    }

    /**
     * Emoji字符串转换为原形
     * @param string $content
     * @return string
     */
    public static function emojiDecode($content) {
        return json_decode(preg_replace_callback('/\\\\\\\\/i', function() {
                    return '\\';
                }, json_encode($content)));
    }

    /**
     * 字节格式化 把字节数格式为 B K M G T 描述的大小
     * @return string
     */
    static public function byte_format($size, $dec = 2) {
        $a = ["B", "KB", "MB", "GB", "TB", "PB"];
        $pos = 0;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, $dec) . " " . $a[$pos];
    }

    /**
     * 压缩css
     * @access public
     * @param string $content
     * @return string
     */
    public function compress_css(string &$content) {
        $content = str_replace(["\r\n", "\r", "\n", "\t", "  ", "    ", "    "], '', preg_replace("!/\*[^*]*\*+([^/][^*]*\*+)*/!", "", $content));
        return $content;
    }

    /**
     * 压缩HTML
     * @access public
     * @param string $content
     * @return string
     */
    public static function compress_html(string &$content) {
        $content = preg_replace(['/\?><\?php/', '~>\s+<~', '~>(\s+\n|\r)~', "/> *([^ ]*) *</", "/[\s]+/", "/<!--[^!]*-->/", "/ \"/", "'/\*[^*]*\*/'"], ['', '><', '>', ">\\1<", ' ', '', "\"", ''], $content);
        return $content;
    }

    /**
     * @static
     * 防止XSS攻击代码——0
     * 删除html和php标签，使得结果只剩下文本
     * 注意<br>不会被删除，同时\n等换行符号也会转换为<br>
     * @param array|string $data
     * @return string
     */
    public static function xssToText($data) {
        if (is_array($data)) {
            return array_map(__METHOD__, $data);
        }
        $data = trim(strip_tags($data, '<br />'));
        $data = self::_del_redundance($data);
        $data = nl2br($data);
        $data = addslashes($data);
        return $data;
    }

    /**
     * @static
     * 防止XSS攻击代码
     */
    public static function remove_xss($val) {
        // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
        // this prevents some character re-spacing such as <java\0script>
        // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
        $val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val);

        // straight replacements, the user should never need these since they're normal characters
        // this prevents like <IMG SRC=@avascript:alert('XSS')>
        $search = 'abcdefghijklmnopqrstuvwxyz';
        $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $search .= '1234567890!@#$%^&*()';
        $search .= '~`";:?+/={}[]-_|\'\\';
        for ($i = 0; $i < strlen($search); $i++) {
            // ;? matches the ;, which is optional
            // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars
            // @ @ search for the hex values
            $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val); // with a ;
            // @ @ 0{0,7} matches '0' zero to seven times
            $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val); // with a ;
        }

        // now the only remaining whitespace attacks are \t, \n, and \r
        $ra1 = ['javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base'];
        $ra2 = ['onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload'];
        $ra = array_merge($ra1, $ra2);

        $found = true; // keep replacing as long as the previous round replaced something
        while ($found == true) {
            $val_before = $val;
            for ($i = 0; $i < sizeof($ra); $i++) {
                $pattern = '/';
                for ($j = 0; $j < strlen($ra[$i]); $j++) {
                    if ($j > 0) {
                        $pattern .= '(';
                        $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                        $pattern .= '|';
                        $pattern .= '|(&#0{0,8}([9|10|13]);)';
                        $pattern .= ')*';
                    }
                    $pattern .= $ra[$i][$j];
                }
                $pattern .= '/i';
                $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // add in <> to nerf the tag
                $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
                if ($val_before == $val) {
                    // no replacements were made, so exit the loop
                    $found = false;
                }
            }
        }
        return $val;
    }

    /**
     * 过滤恶意重复空白
     * @param   string  $txt 原文本
     * @return  string  处理后的文本
     */
    public static function _del_redundance($txt) {
        $txt = preg_replace('/\s+(\r?\n)/', '$1', $txt); //去除恶意空格
        $txt = preg_replace('/\s{8,}/', ' ', $txt); //去除恶意空格
        $txt = preg_replace('/(\r?\n){3,}/', '$1', $txt); //去除恶意换行
        return $txt;
    }

    /**
     * @static
     * 转化文本中的换行符号为<br/>
     * @param array|string $data 需要过滤的字符串
     * @return array|string
     */
    public static function nl2br($data) {
        if (is_array($data)) {
            return array_map(__METHOD__, $data);
        }
        return trim(preg_replace('/(\015)?(\012)/', '<br/>', $data));
    }

    /**
     * @static
     * 删除文本中的换行符号
     * @param array|string $data 需要过滤的字符串
     * @return array|string
     */
    public static function nl2delete($data) {
        if (is_array($data)) {
            return array_map(__METHOD__, $data);
        }
        return trim(preg_replace('/(\015)?(\012)/', '', $data));
    }

    /**
     * 检查字符串中是否包含某些字符串
     * @param string       $haystack
     * @param string|array $needles
     * @return bool
     */
    public static function contains($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查字符串是否以某些字符串结尾
     *
     * @param  string       $haystack
     * @param  string|array $needles
     * @return bool
     */
    public static function endsWith($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ((string) $needle === static::substr($haystack, -static::length($needle))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查字符串是否以某些字符串开头
     *
     * @param  string       $haystack
     * @param  string|array $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 字符串转小写
     *
     * @param  string $value
     * @return string
     */
    public static function lower($value) {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * 字符串转大写
     *
     * @param  string $value
     * @return string
     */
    public static function upper($value) {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * 获取字符串的长度
     *
     * @param  string $value
     * @return int
     */
    public static function length($value) {
        return mb_strlen($value);
    }

    /**
     * 截取字符串
     *
     * @param  string   $string
     * @param  int      $start
     * @param  int|null $length
     * @return string
     */
    public static function substr($string, $start, $length = null) {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

}
