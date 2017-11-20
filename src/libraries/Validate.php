<?php

namespace framework\libraries;

/**
 * 数据验证类
 */
class Validate {

    /**
     * 检查是否为空
     * @param type $str
     * @param type $trim    是否去除两侧空格
     * @return boolean
     */
    public static function notEmpty($str, $trim = true) {
        if (is_array($str)) {
            return 0 < count($str);
        }
        return strlen($trim ? trim($str) : $str) ? true : false;
    }

    /**
     * 使用正则表达式检验
     * @param type $value
     * @param type $regex   表达式
     * @return boolean
     */
    public static function match($value, $regex) {
        return preg_match($regex, $value) ? true : false;
    }

    /**
     * Max
     *
     * @param mixed $value numbernic|string
     * @param number $max
     * @return boolean
     */
    public static function max($value, $max) {
        is_string($value) && $value = mb_strlen($value);
        return $value <= $max;
    }

    /**
     * Min
     *
     * @param mixed $value numbernic|string
     * @param number $min
     * @return boolean
     */
    public static function min($value, $min) {
        is_string($value) && $value = mb_strlen($value);
        return $value >= $min;
    }

    /**
     * Range
     *  判断字符串长度范围
     * @param mixed $value numbernic|string
     * @param array $max
     * @return boolean
     */
    public static function range($value, $range) {
        is_string($value) && $value = mb_strlen($value);
        return (($value >= $range[0]) && ($value <= $range[1]));
    }

    /**
     * 在两个数之间，不包含这2个数字
     * Validate::between(值, 最小值, 最大值)
     * @param type $value
     * @param type $min
     * @param type $max
     * @return boolean
     */
    public static function between($value, $min, $max) {
        return $value > $min && $value < $max;
    }

    /**
     * 在两个数之间，包含这2个数字
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function betweenEqual($value, $min, $max) {
        return $value >= $min && $value <= $max;
    }

    /**
     * 小于
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function lt($value, $num) {
        return $value < $num;
    }

    /**
     * 小于等于
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function ltEqual($value, $num) {
        return $value <= $num;
    }

    /**
     * 大于
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function gt($value, $num) {
        return $value > $num;
    }

    /**
     * 大于等于
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function gtEqual($value, $num) {
        return $value >= $num;
    }

    /**
     * 等于
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function equal($value, $num) {
        return $value == $num;
    }

    /**
     * 不等于
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function unequal($value, $num) {
        return $value != $num;
    }

    /**
     * 值在范围内
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function in($value, $nums) {
        if (!is_array($nums)) {
            $nums = explode(',', $nums);
        }
        return in_array($value, $nums);
    }

    /**
     * 判断值不在范围内
     *
     * @param numeric $value
     * @param array $param
     * @return boolean
     */
    public static function notin($value, $nums) {
        if (!is_array($nums)) {
            $nums = explode(',', $nums);
        }
        return !in_array($value, $nums);
    }

    /**
     * 检测是否为QQ
     * @param type $qq
     * @return boolean
     */
    public static function qq($qq) {
        if (empty($qq)) {
            return false;
        }
        if (is_numeric($qq)) {
            if (preg_match("/^([1-9][0-9]\d{3,11})$/", $qq)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 手机号码的合法性检测
     * @param string $mobile
     * @return boolean
     */
    public static function mobile($mobile) {
        if (empty($mobile)) {
            return false;
        }
        return preg_match('/^1[3|4|5|6|7|8|9][0-9]\d{8,8}$/', $mobile) ? true : false;
    }

    /**
     * 电话(传真)号码合法性检测
     * @return boolean true表示合法，false表示非法
     */
    static public function phone($value) {
        if (empty($value)) {
            return false;
        }
        /* 本地固话 */
        if (mb_strlen($value) == 7) {
            return preg_match("/^([2-9]\d{6})$/", $value) ? true : false;
        }
        /* 本地固话 带分机号码 */
        $rs = preg_match("/^(\d+){7}-[0-9]{3,4}$/", $value) ? true : false;
        if ($rs) {
            return true;
        }
        /* 完整国内固话带分机号码 */
        $rs = preg_match("/^(\d){3,4}-(\d+){7,9}-[0-9]{3,4}$/", $value) ? true : false;
        if ($rs) {
            return true;
        }

        /* 检测中国电话号码格式，支持400、800等 */
        $rs = preg_match('/^(\d{3,4}-)?(\d{7,8}){1}(-\d{2,4})?$/', $value) ? true : false;
        if ($rs) {
            return true;
        }
        $rs = preg_match('/^(\d{3,4}-)?(\d{3,4}){1}(-\d{3,4})?$/', $value) ? true : false;
        if ($rs) {
            return true;
        }

        /* 外地电话 */
        return preg_match("/^(\d){3,4}-(\d+){7,9}$/", $value) ? true : false;
    }

    /**
     * 邮编合法性检测
     * @return boolean true表示合法，false表示非法
     */
    public static function postcode($postcode) {
        return (is_numeric($postcode) && (strlen($postcode) == 6));
    }

    /**
     * 验证码合法性检测
     * @return boolean true表示合法，false表示非法
     */
    public static function captcha($captcha) {
        return (is_numeric($captcha) && (strlen($captcha) == 6));
    }

    /**
      根据〖中华人民共和国国家标准 GB 11643-1999〗中有关公民身份号码的规定，公民身份号码是特征组合码，由十七位数字本体码和一位数字校验码组成。排列顺序从左至右依次为：六位数字地址码，八位数字出生日期码，三位数字顺序码和一位数字校验码。
      地址码表示编码对象常住户口所在县(市、旗、区)的行政区划代码。
      出生日期码表示编码对象出生的年、月、日，其中年份用四位数字表示，年、月、日之间不用分隔符。
      顺序码表示同一地址码所标识的区域范围内，对同年、月、日出生的人员编定的顺序号。顺序码的奇数分给男性，偶数分给女性。
      校验码是根据前面十七位数字码，按照ISO 7064:1983.MOD 11-2校验码计算出来的检验码。

      出生日期计算方法。
      15位的身份证编码首先把出生年扩展为4位，简单的就是增加一个19或18,这样就包含了所有1800-1999年出生的人;
      2000年后出生的肯定都是18位的了没有这个烦恼，至于1800年前出生的,那啥那时应该还没身份证号这个东东，⊙﹏⊙b汗...
      下面是正则表达式:
      出生日期1800-2099  (18|19|20)?\d{2}(0[1-9]|1[12])(0[1-9]|[12]\d|3[01])
      身份证正则表达式 /^\d{6}(18|19|20)?\d{2}(0[1-9]|1[12])(0[1-9]|[12]\d|3[01])\d{3}(\d|X)$/i
      15位校验规则 6位地址编码+6位出生日期+3位顺序号
      18位校验规则 6位地址编码+8位出生日期+3位顺序号+1位校验位

      校验位规则     公式:∑(ai×Wi)(mod 11)……………………………………(1)
      公式(1)中：
      i----表示号码字符从由至左包括校验码在内的位置序号；
      ai----表示第i位置上的号码字符值；
      Wi----示第i位置上的加权因子，其数值依据公式Wi=2^(n-1）(mod 11)计算得出。
      i 18 17 16 15 14 13 12 11 10 9 8 7 6 5 4 3 2 1
      Wi 7 9 10 5 8 4 2 1 6 3 7 9 10 5 8 4 2 1
     */

    /**
     * 严格的身份证号码合法性检测(按照身份证生成算法进行检查)
     * @param type $value
     * @return boolean
     */
    static public function idcard($value) {
        if (empty($value)) {
            return false;
        }
        $city_array = [
            11 => "北京",
            12 => "天津",
            13 => "河北",
            14 => "山西",
            15 => "内蒙古",
            21 => "辽宁",
            22 => "吉林",
            23 => "黑龙江",
            31 => "上海",
            32 => "江苏",
            33 => "浙江",
            34 => "安徽",
            35 => "福建",
            36 => "江西",
            37 => "山东",
            41 => "河南",
            42 => "湖北",
            43 => "湖南",
            44 => "广东",
            45 => "广西",
            46 => "海南",
            50 => "重庆",
            51 => "四川",
            52 => "贵州",
            53 => "云南",
            54 => "西藏 ",
            61 => "陕西",
            62 => "甘肃",
            63 => "青海",
            64 => "宁夏",
            65 => "新疆",
            71 => "台湾",
            81 => "香港",
            82 => "澳门",
            91 => "国外"
        ];
        $city = intval($value[0] . $value[1]);
        if (!array_key_exists($city, $city_array)) {
            /* 地址编码错误 */
            return false;
        }

        if (!preg_match('/^\d{6}(18|19|20)?\d{2}(0[1-9]|1[12])(0[1-9]|[12]\d|3[01])\d{3}(\d|X)$/i', $value)) {
            /* 身份证号格式错误 */
            return false;
        }

        $wi = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $ai = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $value = strtoupper($value);
        $sigma = '';
        for ($i = 0; $i < 17; $i++) {
            $sigma += ((int) $value{$i}) * $wi[$i];
        }
        $parity_bit = $ai[($sigma % 11)];
        if ($parity_bit != substr($value, -1)) {
            /* 校验位错误 */
            return false;
        }
        return true;
    }

    /**
     * 检查是否为常见网址格式
     * @param string $url
     * @return boolean
     */
    public static function url($url) {
        if (empty($url)) {
            return false;
        }
        return preg_match('#^(http|https|ftp|ftps)://([\w-]+\.)+[\w-]+(/[\w-./?%&=]*)?#i', $url) ? true : false;
    }

    /**
     * 检查是否为日期格式
     * @param string $date
     * @return boolean
     */
    public static function date($date) {
        return preg_match('/^\d{4}[\/-]\d{1,2}[\/-]\d{1,2}$/', $date) ? true : false;
    }

    /**
     * 验证日期是否在某日之后
     * @access protected
     * @param mixed     $value  字段值
     * @param mixed     $rule  验证规则
     * @return bool
     */
    public function date_after($value, $rule) {
        return strtotime($value) >= strtotime($rule);
    }

    /**
     * 验证日期是否在某日之前
     * @access protected
     * @param mixed     $value  字段值
     * @param mixed     $rule  验证规则
     * @return bool
     */
    public function date_before($value, $rule) {
        return strtotime($value) <= strtotime($rule);
    }

    /**
     * 验证日期是否在有效期范围内
     * @access protected
     * @param mixed     $value  字段值
     * @param mixed     $rule  验证规则
     * @return bool
     */
    public function date_expire($value, $rule) {
        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }
        list($start, $end) = $rule;
        if (!is_numeric($start)) {
            $start = strtotime($start);
        }

        if (!is_numeric($end)) {
            $end = strtotime($end);
        }

        $value = strtotime($value);
        return ($value >= $start) && ($value <= $end);
    }

    /**
     * Check if is datetime
     *
     * @param string $datetime
     * @return boolean
     */
    public static function datetime($datetime, $format = 'Y-m-d H:i:s') {
        return ($time = strtotime($datetime)) && ($datetime == date($format, $time));
    }

    /**
     * Check if is numbers
     *
     * @param mixed $value
     * @return boolean
     */
    public static function number($value) {
        return is_numeric($value);
    }

    /**
     * Check if is digit
     *
     * @param mixed $value
     * @return boolean
     */
    public static function digit($value) {
        return is_int($value) || ctype_digit($value);
    }

    /**
     * 验证是否是中文
     * 注：字符串编码仅支持UTF-8
     * @param string $string 待验证的字串
     * @return boolean 如果是中文则返回true，否则返回false
     */
    public static function isChinese($string) {
        return (boolean) preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $string);
    }

    /**
     * 验证是否是字母
     *
     * @param string $string
     * @return boolean
     */
    public static function isAlpha($string) {
        return (boolean) preg_match('/^[A-Za-z]+$/', $string);
    }

    /**
     * 验证是否是字母和数字
     *
     * @param string $string
     * @return boolean
     */
    public static function isAlphaNum($string) {
        return (boolean) preg_match('/^[A-Za-z0-9]+$/', $string);
    }

    /**
     * 验证是否是字母、数字和下划线 破折号
     *
     * @param string $string
     * @return boolean
     */
    public static function isAlphaDash($string) {
        return (boolean) preg_match('/^[A-Za-z0-9\-\_]+$/', $string);
    }

    /**
     * 验证是否是有合法的email
     *
     * @param string $email
     * @return boolean
     */
    public static function isEmail($email) {
        $atIndex = strrpos($email, '@');
        if (false === $atIndex) {
            return false;
        } else {
            $domain = substr($email, $atIndex + 1);
            $local = substr($email, 0, $atIndex);
            if (!strpos($domain, '.')) {
                return false;
            } else {
                $localLen = strlen($local);
                $domainLen = strlen($domain);
                if ($localLen < 1 || $localLen > 64) {
                    // 本地部分长度超过
                    return false;
                } else if ($domainLen < 1 || $domainLen > 255) {
                    // 超出域部分长度
                    return false;
                } else if ('.' === $local[0] || '.' === $local[$localLen - 1]) {
                    // 本地部分开始或结尾'.'
                    return false;
                } else if (preg_match('/\\.\\./', $local)) {
                    // 本地部分已经连续两个点
                    return false;
                } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                    // 字符不是有效的域部分
                    return false;
                } else if (preg_match('/\\.\\./', $domain)) {
                    // 域部分已经连续两个点
                    return false;
                } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace('\\\\', '', $local))) {
                    // 除非本地部分是引用字符不是有效的本地部分
                    if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace('\\\\', '', $local))) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * 验证是否是有合法的IP
     *
     * @param string $ip
     * @return boolean
     */
    public static function isIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
    }

    /**
     * 验证是否是有合法的IP4
     *
     * @param string $ip
     * @return boolean
     */
    public static function isIP4($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 验证是否是有合法的IP6
     *
     * @param string $ip
     * @return boolean
     */
    public static function isIP6($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 验证是否为浮点数
     *
     * @param string $float
     * @return boolean
     */
    public static function isFloat($float) {
        return filter_var($float, FILTER_VALIDATE_FLOAT);
    }

    /**
     * 验证是否为整数
     *
     * @param string $number
     * @return boolean
     */
    public static function is_number($number) {
        return filter_var($number, FILTER_VALIDATE_INT);
    }

    /**
     * 验证是否为整数
     *
     * @param string $number
     * @return boolean
     */
    public static function is_integer($number) {
        return self::is_number($number);
    }

    /**
     * 验证是否为布尔值
     *
     * @param string $boolean
     * @return boolean
     */
    public static function is_boolean($boolean) {
        return filter_var($boolean, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 验证是数字ＩＤ
     *
     * @param int $number 需要被验证的数字
     * @return boolean 如果大于等于0的整数数字返回true，否则返回false
     */
    public static function is_number_id($number) {
        return preg_match('/^[1-9][0-9]*$/i', $number);
    }

    /**
     * 查字符串是否是UTF8编码
     *
     * @param string $string 字符
     * @return boolean
     */
    public static function isUtf8($string) {
        $c = 0;
        $b = 0;
        $bits = 0;
        $len = strlen($string);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($string[$i]);
            if ($c > 128) {
                if (($c >= 254)) {
                    return false;
                } elseif ($c >= 252) {
                    $bits = 6;
                } elseif ($c >= 248) {
                    $bits = 5;
                } elseif ($c >= 240) {
                    $bits = 4;
                } elseif ($c >= 224) {
                    $bits = 3;
                } elseif ($c >= 192) {
                    $bits = 2;
                } else {
                    return false;
                }

                if (($i + $bits) > $len) {
                    return false;
                }
                while ($bits > 1) {
                    $i++;
                    $b = ord($string[$i]);
                    if ($b < 128 || $b > 191) {
                        return false;
                    }
                    $bits--;
                }
            }
        }
        return true;
    }

    /**
     * 检测是否包含特殊字符
     * @return boolean true表示含有特殊字符，false表示不含有特殊字符
     */
    static public function is_special_word($string) {
        return (boolean) preg_match('/>|<|,|\[|\]|\{|\}|\?|\/|\+|=|\||\'|\\|\"|:|;|\~|\!|\@|\*|\$|\%|\^|\&|\(|\)|`/i', $string);
    }

    /**
     * 验证字符串中是否含有非法字符
     *
     * @param string $string	待验证的字符串
     * @return boolean
     */
    static public function is_invalid_str($string) {
        if (!$string) {
            return false;
        }
        return preg_match('#[!#$%^&*(){}~`"\';:?+=<>/\[\]]+#', $string) ? true : false;
    }

    /**
     * 用正则表达式验证出版物的ISBN号
     *
     * @param integer $str	所要验证的ISBN号,通常是由13位数字构成
     * @return boolean
     */
    static public function is_book_isbn($str) {
        if (!$str) {
            return false;
        }
        return preg_match('#^978[\d]{10}$|^978-[\d]{10}$#', $str) ? true : false;
    }

    /**
     * 检测一个用户名的合法性
     *
     * @param string $str 需要检查的用户名字符串
     * @param int $chkType 要求用户名的类型 1为英文、数字、下划线，2为任意可见字符，3为中文(GBK)、英文、数字、下划线，4为中文(UTF8)、英文、数字，缺省为1
     * @return bool 返回检查结果，合法为true，非法为false
     */
    static public function is_user_name($str, $chkType = 1) {
        switch ($chkType) {
            case 1 :
                $result = preg_match("/^[a-zA-Z0-9_]+$/i", $str);
                break;
            case 2 :
                $result = preg_match("/^[\w\d]+$/i", $str);
                break;
            case 3 :
                $result = preg_match("/^[_a-zA-Z0-9\0x80-\0xff]+$/i", $str);
                break;
            case 4 :
                $result = preg_match("/^[_a-zA-Z0-9\u4e00-\u9fa5]+$/i", $str);
                break;
            default :
                $result = preg_match("/^[a-zA-Z0-9_]+$/i", $str);
                break;
        }
        return $result;
    }

    /**
     * 检查密码长度是否符合规定
     *
     * @param STRING $password
     * @return 	TRUE or FALSE
     */
    static public function is_password($password) {
        $strlen = strlen($password);
        if ($strlen >= 6 && $strlen <= 32) {
            $score = 1;
            if (preg_match("/[0-9]+/", $password)) {
                $score ++;
            }
            if (preg_match("/[0-9]{3,}/", $password)) {
                $score ++;
            }
            if (preg_match("/[a-z]+/", $password)) {
                $score ++;
            }
            if (preg_match("/[a-z]{3,}/", $password)) {
                $score ++;
            }
            if (preg_match("/[A-Z]+/", $password)) {
                $score ++;
            }
            if (preg_match("/[A-Z]{3,}/", $password)) {
                $score ++;
            }
            if (preg_match("/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]+/", $password)) {
                $score += 2;
            }
            if (preg_match("/[_|\-|+|=|*|!|@|#|$|%|^|&|(|)]{3,}/", $password)) {
                $score ++;
            }
            if ($strlen > 8) {
                $score ++;
            }
            if ($score > 5) {
                return true;
            }
        }
        return false;
    }

}
