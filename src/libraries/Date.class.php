<?php

/**
 * 日期时间操作类
 */
class Date {

    /**
     * 日期的时间戳
     * @var integer
     * @access protected
     */
    protected $date;

    /**
     * 时区
     * @var integer
     * @access protected
     */
    protected $timezone;

    /**
     * 年
     * @var integer
     * @access protected
     */
    protected $year;

    /**
     * 月
     * @var integer
     * @access protected
     */
    protected $month;

    /**
     * 日
     * @var integer
     * @access protected
     */
    protected $day;

    /**
     * 时
     * @var integer
     * @access protected
     */
    protected $hour;

    /**
     * 分
     * @var integer
     * @access protected
     */
    protected $minute;

    /**
     * 秒
     * @var integer
     * @access protected
     */
    protected $second;

    /**
     * 星期的数字表示
     * @var integer
     * @access protected
     */
    protected $weekday;

    /**
     * 星期的完整表示
     * @var string
     * @access protected
     */
    protected $cWeekday;

    /**
     * 一年中的天数 0－365
     * @var integer
     * @access protected
     */
    protected $yDay;

    /**
     * 月份的完整表示
     * @var string
     * @access protected
     */
    protected $cMonth;

    /**
     * 日期CDATE表示
     * @var string
     * @access protected
     */
    protected $CDATE;

    /**
     * 日期的YMD表示
     * @var string
     * @access protected
     */
    protected $YMD;

    /**
     * 时间的输出表示
     * @var string
     * @access protected
     */
    protected $CTIME;
    // 星期的输出
    protected $Week = array("日", "一", "二", "三", "四", "五", "六");

    /**
     * 架构函数
     * 创建一个Date对象
     * @param mixed $date  日期
     * @static
     * @access public
     */
    public function __construct($date = '') {
        //分析日期
        $this->date = $this->parse($date);
        $this->setDate($this->date);
    }

    /**
     * 日期分析
     * 返回时间戳
     * @static
     * @access public
     * @param mixed $date 日期
     * @return string
     */
    public function parse($date) {
        if (is_string($date)) {
            if (($date == "") || strtotime($date) == -1) {
                //为空默认取得当前时间戳
                $tmpdate = time();
            } else {
                //把字符串转换成UNIX时间戳
                $tmpdate = strtotime($date);
            }
        } elseif (is_null($date)) {
            //为空默认取得当前时间戳
            $tmpdate = time();
        } elseif (is_numeric($date)) {
            //数字格式直接转换为时间戳
            $tmpdate = $date;
        } else {
            if (is_object($date) && get_class($date) == "Date") {
                //如果是Date对象
                $tmpdate = $date->date;
            } else {
                //默认取当前时间戳
                $tmpdate = time();
            }
        }
        return $tmpdate;
    }

    /**
     * 日期参数设置
     * @static
     * @access public
     * @param integer $date  日期时间戳
     * @return void
     */
    public function setDate($date) {
        $dateArray = getdate($date);
        $this->date = $dateArray[0];            //时间戳
        $this->second = $dateArray["seconds"];    //秒
        $this->minute = $dateArray["minutes"];    //分
        $this->hour = $dateArray["hours"];      //时
        $this->day = $dateArray["mday"];       //日
        $this->month = $dateArray["mon"];        //月
        $this->year = $dateArray["year"];       //年

        $this->weekday = $dateArray["wday"];       //星期 0～6
        $this->cWeekday = '星期' . $this->Week[$this->weekday]; //$dateArray["weekday"];    //星期完整表示
        $this->yDay = $dateArray["yday"];       //一年中的天数 0－365
        $this->cMonth = $dateArray["month"];      //月份的完整表示

        $this->CDATE = $this->format("%Y-%m-%d"); //日期表示
        $this->YMD = $this->format("%Y%m%d");  //简单日期
        $this->CTIME = $this->format("%H:%M:%S"); //时间表示

        return;
    }

    /**
     * 返回 星期 0～6
     * @return type
     */
    public function get_week_day() {
        return $this->weekday;
    }

    /**
     * 返回 星期完整表示
     * @param type $format
     * @return type
     */
    public function get_week_day_format() {
        return $this->cWeekday;
    }

    /**
     * 日期格式化
     * 默认返回 1970-01-01 11:30:45 格式
     * @access public
     * @param string $format  格式化参数
     * @return string
     */
    public function format($format = "%Y-%m-%d %H:%M:%S") {
        return strftime($format, $this->date);
    }

    /**
     * 是否为闰年
     * @static
     * @access public
     * @return string
     */
    public function isLeapYear($year = '') {
        if (empty($year)) {
            $year = $this->year;
        }
        return ((($year % 4) == 0) && (($year % 100) != 0) || (($year % 400) == 0));
    }

    /**
     * 计算日期差
     *
     *  w - weeks
     *  d - days
     *  h - hours
     *  m - minutes
     *  s - seconds
     * @static
     * @access public
     * @param mixed $date 要比较的日期
     * @param string $elaps  比较跨度
     * @return integer
     */
    public function dateDiff($date, $elaps = "d") {
        $__DAYS_PER_WEEK__ = (7);
        $__DAYS_PER_MONTH__ = (30);
        $__DAYS_PER_YEAR__ = (365);
        $__HOURS_IN_A_DAY__ = (24);
        $__MINUTES_IN_A_DAY__ = (1440);
        $__SECONDS_IN_A_DAY__ = (86400);
        //计算天数差
        $__DAYSELAPS = ceil(($this->parse($date) - $this->date) / $__SECONDS_IN_A_DAY__);
        switch ($elaps) {
            case "y"://转换成年
                $__DAYSELAPS = $__DAYSELAPS / $__DAYS_PER_YEAR__;
                break;
            case "M"://转换成月
                $__DAYSELAPS = $__DAYSELAPS / $__DAYS_PER_MONTH__;
                break;
            case "w"://转换成星期
                $__DAYSELAPS = $__DAYSELAPS / $__DAYS_PER_WEEK__;
                break;
            case "h"://转换成小时
                $__DAYSELAPS = $__DAYSELAPS * $__HOURS_IN_A_DAY__;
                break;
            case "m"://转换成分钟
                $__DAYSELAPS = $__DAYSELAPS * $__MINUTES_IN_A_DAY__;
                break;
            case "s"://转换成秒
                $__DAYSELAPS = $__DAYSELAPS * $__SECONDS_IN_A_DAY__;
                break;
        }
        return $__DAYSELAPS;
    }

    /**
     * 人性化的计算日期差
     * @static
     * @access public
     * @param mixed $time 要比较的时间
     * @param mixed $precision 返回的精度
     * @return string
     */
    public function timeDiff($time, $precision = false) {
        if (!is_numeric($precision) && !is_bool($precision)) {
            static $_diff = array('y' => '年', 'M' => '个月', 'd' => '天', 'w' => '周', 's' => '秒', 'h' => '小时', 'm' => '分钟');
            return ceil($this->dateDiff($time, $precision)) . $_diff[$precision] . '前';
        }
        $diff = abs($this->parse($time) - $this->date);
        static $chunks = array(array(31536000, '年'), array(2592000, '个月'), array(604800, '周'), array(86400, '天'), array(3600, '小时'), array(60, '分钟'), array(1, '秒'));
        $count = 0;
        $since = '';
        for ($i = 0; $i < count($chunks); $i++) {
            if ($diff >= $chunks[$i][0]) {
                $num = floor($diff / $chunks[$i][0]);
                $since .= sprintf('%d' . $chunks[$i][1], $num);
                $diff = (int) ($diff - $chunks[$i][0] * $num);
                $count++;
                if (!$precision || $count >= $precision) {
                    break;
                }
            }
        }
        return $since . '前';
    }

    /**
     * 友好的时间显示
     *
     * @param  int    $sTime 待显示的时间
     * @param  string $type  类型. normal | mohu | full | ymd | other
     * @return string
     */
    public function friendlyDate($sTime, $type = 'mohu') {
        if (!$sTime) {
            return '';
        }
        //sTime=源时间，cTime=当前时间，dTime=时间差
        $cTime = time();
        $dTime = $cTime - $sTime;
        $dDay = intval(date('z', $cTime)) - intval(date('z', $sTime));
        $dYear = intval(date('Y', $cTime)) - intval(date('Y', $sTime));

        if ($type == 'mohu') {
            if ($dYear > 0) {
                return date('Y-n-j', $sTime);
            }
            if ($dTime < 60) {
                return '刚刚';
            } elseif ($dTime < 3600) {
                return intval($dTime / 60) . '分钟内';
            } elseif ($dTime >= 3600 && $dDay == 0) {
                return intval($dTime / 3600) . '小时内';
            } elseif ($dDay > 0 && $dDay <= 3) {
                return intval($dDay) . '天内';
            } elseif ($dDay > 0 && $dDay > 3 && $dDay <= 7) {
                return '本周内';
            } elseif ($dDay > 7 && $dDay <= 30) {
                return intval($dDay / 7) . '周内';
            } elseif ($dYear == 0) {
                return date('n月j日', $sTime);
            } else {
                return date('Y-n-j', $sTime);
            }
            //full: Y-m-d , H:i:s
        } elseif ($type == 'full') {
            return date('Y-n-j , G:i:s', $sTime);
        } elseif ($type == 'ymd') {
            return date('Y-m-d', $sTime);
        }

        //normal：n秒前，n分钟前，n小时前，日期
        if ($dTime < 60) {
            if ($dTime < 10) {
                return '刚刚';
            } else {
                return intval(floor($dTime / 10) * 10) . '秒前';
            }
        } elseif ($dTime < 3600) {
            return intval($dTime / 60) . '分钟前';
            //今天的数据.年份相同.日期相同.
        } elseif ($dYear == 0 && $dDay == 0) {
            return '今天' . date('H:i', $sTime);
        } elseif ($dYear == 0) {
            return date('n月j日 G:i', $sTime);
        }
        return date('Y-n-j G:i', $sTime);
    }

    /**
     * 返回周的某一天 返回Date对象
     * @access public
     * @return Date
     */
    public function getDayOfWeek($n) {
        $week = array(0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday');
        return (new Date($week[$n]));
    }

    /**
     * 计算周的第一天 返回Date对象
     * @access public
     * @return Date
     */
    public function firstDayOfWeek() {
        return $this->getDayOfWeek(1);
    }

    /**
     * 计算月份的第一天 返回Date对象
     * @access public
     * @return Date
     */
    public function firstDayOfMonth() {
        return (new Date(mktime(0, 0, 0, $this->month, 1, $this->year)));
    }

    /**
     * 计算年份的第一天 返回Date对象
     * @access public
     * @return Date
     */
    public function firstDayOfYear() {
        return (new Date(mktime(0, 0, 0, 1, 1, $this->year)));
    }

    /**
     * 计算周的最后一天 返回Date对象
     * @access public
     * @return Date
     */
    public function lastDayOfWeek() {
        return $this->getDayOfWeek(0);
    }

    /**
     * 计算月份的最后一天 返回Date对象
     * @access public
     * @return Date
     */
    public function lastDayOfMonth() {
        return (new Date(mktime(0, 0, 0, $this->month + 1, 0, $this->year)));
    }

    /**
     * 计算年份的最后一天 返回Date对象
     * @access public
     * @return Date
     */
    public function lastDayOfYear() {
        return (new Date(mktime(0, 0, 0, 1, 0, $this->year + 1)));
    }

    /**
     * 计算月份的最大天数
     * @access public
     * @return integer
     */
    public function maxDayOfMonth() {
        $result = $this->dateDiff(strtotime($this->dateAdd(1, 'm')), 'd');
        return $result;
    }

    /**
     * 取得指定间隔日期
     *
     *    yyyy - 年
     *    q    - 季度
     *    m    - 月
     *    y    - day of year
     *    d    - 日
     *    w    - 周
     *    ww   - week of year
     *    h    - 小时
     *    n    - 分钟
     *    s    - 秒
     * @access public
     * @param integer $number 间隔数目
     * @param string $interval  比较类型
     * @return Date
     */
    public function dateAdd($number = 0, $interval = "d") {
        $hours = $this->hour;
        $minutes = $this->minute;
        $seconds = $this->second;
        $month = $this->month;
        $day = $this->day;
        $year = $this->year;

        switch ($interval) {
            case "yyyy":
                //---Add $number to year
                $year += $number;
                break;

            case "q":
                //---Add $number to quarter
                $month += ($number * 3);
                break;

            case "m":
                //---Add $number to month
                $month += $number;
                break;

            case "y":
            case "d":
            case "w":
                //---Add $number to day of year, day, day of week
                $day += $number;
                break;

            case "ww":
                //---Add $number to week
                $day += ($number * 7);
                break;

            case "h":
                //---Add $number to hours
                $hours += $number;
                break;

            case "n":
                //---Add $number to minutes
                $minutes += $number;
                break;

            case "s":
                //---Add $number to seconds
                $seconds += $number;
                break;
        }

        return (new Date(mktime($hours, $minutes, $seconds, $month, $day, $year)));
    }

    /**
     * 根据生日时间戳，计算年龄
     * @param type $birthday
     */
    public function age($birthday) {
        if (!is_numeric($birthday)) {
            $birthday = strtotime($birthday);
        }
        $age = date('Y') - date('Y', $birthday) - 1;
        if (date('m') == date('m', $birthday)) {
            if (date('d') > date('d', $birthday)) {
                $age++;
            }
        } elseif (date('m') > date('m', $birthday)) {
            $age++;
        }
        return $age;
    }

    /**
     * 日期数字转中文
     * 用于日和月、周
     * @static
     * @access public
     * @param integer $number 日期数字
     * @return string
     */
    public function numberToCh($number) {
        $number = intval($number);
        $array = array('一', '二', '三', '四', '五', '六', '七', '八', '九', '十');
        $str = '';
        if ($number == 0) {
            $str .= "十";
        }
        if ($number < 10) {
            $str .= $array[$number - 1];
        } elseif ($number < 20) {
            $str .= "十" . $array[$number - 11];
        } elseif ($number < 30) {
            $str .= "二十" . $array[$number - 21];
        } else {
            $str .= "三十" . $array[$number - 31];
        }
        return $str;
    }

    /**
     * 年份数字转中文
     * @static
     * @access public
     * @param integer $yearStr 年份数字
     * @param boolean $flag 是否显示公元
     * @return string
     */
    public function yearToCh($yearStr, $flag = false) {
        $array = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
        $str = $flag ? '公元' : '';
        for ($i = 0; $i < 4; $i++) {
            $str .= $array[substr($yearStr, $i, 1)];
        }
        return $str;
    }

    /**
     *  判断日期 所属 干支 生肖 星座
     *  type 参数：XZ 星座 GZ 干支 SX 生肖
     *
     * @static
     * @access public
     * @param string $type  获取信息类型
     * @return string
     */
    public function magicInfo($type) {
        $result = '';
        $m = $this->month;
        $y = $this->year;
        $d = $this->day;

        switch ($type) {
            case 'XZ'://星座
                $XZDict = array('摩羯', '宝瓶', '双鱼', '白羊', '金牛', '双子', '巨蟹', '狮子', '处女', '天秤', '天蝎', '射手');
                $Zone = array(1222, 122, 222, 321, 421, 522, 622, 722, 822, 922, 1022, 1122, 1222);
                if ((100 * $m + $d) >= $Zone[0] || (100 * $m + $d) < $Zone[1])
                    $i = 0;
                else
                    for ($i = 1; $i < 12; $i++) {
                        if ((100 * $m + $d) >= $Zone[$i] && (100 * $m + $d) < $Zone[$i + 1])
                            break;
                    }
                $result = $XZDict[$i] . '座';
                break;

            case 'GZ'://干支
                $GZDict = array(
                    array('甲', '乙', '丙', '丁', '戊', '己', '庚', '辛', '壬', '癸'),
                    array('子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥')
                );
                $i = $y - 1900 + 36;
                $result = $GZDict[0][$i % 10] . $GZDict[1][$i % 12];
                break;

            case 'SX'://生肖
                $SXDict = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
                $result = $SXDict[($y - 4) % 12];
                break;
        }
        return $result;
    }

    /* 农历部份 */

    private $curData = null; //当前阳历时间
    private $ylYeal = 0;
    private $ylMonth = 0;
    private $yldate = 0;
    private $ylDays = 0; //当前日期是农历年的第多少天
    private $leap = 0; //代表润哪一个月
    private $leapDays = 0; //代表闰月的天数
    private $difmonth = 0; //当前时间距离参考时间相差多少月
    private $difDay = 0; //当前时间距离参考时间相差多少天
    private $dataInfo = array(0x04bd8, 0x04ae0, 0x0a570, 0x054d5, 0x0d260, 0x0d950, 0x16554, 0x056a0, 0x09ad0, 0x055d2, //1900-1909
        0x04ae0, 0x0a5b6, 0x0a4d0, 0x0d250, 0x1d255, 0x0b540, 0x0d6a0, 0x0ada2, 0x095b0, 0x14977, //1910-1919
        0x04970, 0x0a4b0, 0x0b4b5, 0x06a50, 0x06d40, 0x1ab54, 0x02b60, 0x09570, 0x052f2, 0x04970, //1920-1929
        0x06566, 0x0d4a0, 0x0ea50, 0x06e95, 0x05ad0, 0x02b60, 0x186e3, 0x092e0, 0x1c8d7, 0x0c950, //1930-1939
        0x0d4a0, 0x1d8a6, 0x0b550, 0x056a0, 0x1a5b4, 0x025d0, 0x092d0, 0x0d2b2, 0x0a950, 0x0b557, //1940-1949
        0x06ca0, 0x0b550, 0x15355, 0x04da0, 0x0a5b0, 0x14573, 0x052b0, 0x0a9a8, 0x0e950, 0x06aa0, //1950-1959
        0x0aea6, 0x0ab50, 0x04b60, 0x0aae4, 0x0a570, 0x05260, 0x0f263, 0x0d950, 0x05b57, 0x056a0, //1960-1969
        0x096d0, 0x04dd5, 0x04ad0, 0x0a4d0, 0x0d4d4, 0x0d250, 0x0d558, 0x0b540, 0x0b6a0, 0x195a6, //1970-1979
        0x095b0, 0x049b0, 0x0a974, 0x0a4b0, 0x0b27a, 0x06a50, 0x06d40, 0x0af46, 0x0ab60, 0x09570, //1980-1989
        0x04af5, 0x04970, 0x064b0, 0x074a3, 0x0ea50, 0x06b58, 0x055c0, 0x0ab60, 0x096d5, 0x092e0, //1990-1999
        0x0c960, 0x0d954, 0x0d4a0, 0x0da50, 0x07552, 0x056a0, 0x0abb7, 0x025d0, 0x092d0, 0x0cab5, //2000-2009
        0x0a950, 0x0b4a0, 0x0baa4, 0x0ad50, 0x055d9, 0x04ba0, 0x0a5b0, 0x15176, 0x052b0, 0x0a930, //2010-2019
        0x07954, 0x06aa0, 0x0ad50, 0x05b52, 0x04b60, 0x0a6e6, 0x0a4e0, 0x0d260, 0x0ea65, 0x0d530, //2020-2029
        0x05aa0, 0x076a3, 0x096d0, 0x04bd7, 0x04ad0, 0x0a4d0, 0x1d0b6, 0x0d250, 0x0d520, 0x0dd45, //2030-2039
        0x0b5a0, 0x056d0, 0x055b2, 0x049b0, 0x0a577, 0x0a4b0, 0x0aa50, 0x1b255, 0x06d20, 0x0ada0, //2040-2049
        0x14b63, 0x09370, 0x049f8, 0x04970, 0x064b0, 0x168a6, 0x0ea50, 0x06b20, 0x1a6c4, 0x0aae0, //2050-2059
        0x0a2e0, 0x0d2e3, 0x0c960, 0x0d557, 0x0d4a0, 0x0da50, 0x05d55, 0x056a0, 0x0a6d0, 0x055d4, //2060-2069
        0x052d0, 0x0a9b8, 0x0a950, 0x0b4a0, 0x0b6a6, 0x0ad50, 0x055a0, 0x0aba4, 0x0a5b0, 0x052b0, //2070-2079
        0x0b273, 0x06930, 0x07337, 0x06aa0, 0x0ad50, 0x14b55, 0x04b60, 0x0a570, 0x054e4, 0x0d160, //2080-2089
        0x0e968, 0x0d520, 0x0daa0, 0x16aa6, 0x056d0, 0x04ae0, 0x0a9d4, 0x0a2d0, 0x0d150, 0x0f252, //2090-2099
        0x0d520);

    private function ylInit() {
        $basedate = '1900-1-31'; //参照日期
        $timezone = 'PRC';
        $datetime = new DateTime($basedate, new DateTimeZone($timezone));
        $curTime = new DateTime($this->curData, new DateTimeZone($timezone));
        $offset = ($curTime->format('U') - $datetime->format('U')) / 86400; //相差的天数
        $offset = ceil($offset);
        $this->difDay = $offset;
        $offset += 1; //只能使用ceil，不能使用intval或者是floor,因为1900-1-31为正月初一，故需要加1
        for ($i = 1900; $i < 2050 && $offset > 0; $i++) {
            $temp = $this->getYearDays($i); //计算i年有多少天
            $offset -= $temp;
            $this->difmonth += 12;
            //判断该年否存在闰月
            if ($this->leapMonth($i) > 0) {
                $this->difmonth += 1;
            }
        }

        if ($offset < 0) {
            $offset += $temp;
            $i--;
            $this->difmonth -= 12;
        }
        if ($this->leapMonth($i) > 0) {
            $this->difmonth -= 1;
        }
        $this->ylDays = $offset;
        //此时$offset代表是农历该年的第多少天
        $this->ylYeal = $i; //农历哪一年
        //计算月份，依次减去1~12月份的天数，直到offset小于下个月的天数
        $curMonthDays = $this->monthDays($this->ylYeal, 1);
        //判断是否该年是否存在闰月以及闰月的天数
        $this->leap = $this->leapMonth($this->ylYeal);
        if ($this->leap != 0) {
            $this->leapDays = $this->leapDays($this->ylYeal);
        }

        for ($i = 1; $i < 13 && $curMonthDays < $offset; $curMonthDays = $this->monthDays($this->ylYeal, ++$i)) {
            if ($this->leap == $i) { //闰月
                if ($offset > $this->leapDays) {
                    --$i;
                    $offset -= $this->leapDays;
                    $this->difmonth += 1;
                } else {
                    break;
                }
            } else {
                $offset -= $curMonthDays;
                $this->difmonth += 1;
            }
        }

        $this->ylMonth = $i;
        $this->yldate = $offset;
    }

    /**
     * 计算农历y年有多少天
     * */
    public function getYearDays($y) {
        $sum = 348; //12*29=348,不考虑小月的情况下
        for ($i = 0x8000; $i >= 0x10; $i >>= 1) {
            $sum += ($this->dataInfo[$y - 1900] & $i) ? 1 : 0;
        }
        return($sum + $this->leapDays($y));
    }

    /**
     * 获取农历某一年闰月的天数
     * */
    public function leapDays($y) {
        if ($this->leapMonth($y)) {
            return(($this->dataInfo[$y - 1900] & 0x10000) ? 30 : 29);
        } else {
            return(0);
        }
    }

    /**
     * 计算农历哪一月为闰月
     */
    public function leapMonth($y) {
        return ($this->dataInfo[$y - 1900] & 0xf);
    }

    /**
     * 计算农历y年m月有多少天
     */
    public function monthDays($y, $m) {
        return (($this->dataInfo[$y - 1900] & (0x10000 >> $m)) ? 30 : 29 );
    }

    /**
     *  获取农历日期
     */
    public function getLunar($curData = null) {
        if (!empty($curData)) {
            $this->curData = $curData;
        } else {
            $this->curData = date('Y-n-j');
        }
        $this->ylInit();

        $tmp = array('初', '一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '廿');
        $dateStr = '';
        if ($this->ylMonth > 10) {
            $m2 = intval($this->ylMonth - 10); //十位
            $dateStr = '十' . $tmp[$m2] . '月';
        } elseif ($this->ylMonth == 1) {
            $dateStr = '正月';
        } else {
            $dateStr = $tmp[$this->ylMonth] . '月';
        }

        if ($this->yldate < 11) {
            $dateStr .= '初' . $tmp[$this->yldate];
        } else {
            $m1 = intval($this->yldate / 10);
            if ($m1 != 3) {
                $dateStr .= ($m1 == 1) ? '十' : '廿';
                $m2 = $this->yldate % 10;
                if ($m2 == 0) {
                    $dateStr .= '十';
                } else {
                    $dateStr .= $tmp[$m2];
                }
            } else {
                $dateStr .= '三十';
            }
        }
        return $dateStr;
    }

    public function __toString() {
        return $this->format();
    }

}
