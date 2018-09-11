<?php

namespace framework\safety;

use framework\nosql\ssdbService;

/**
 * 密保卡
 */
class SecurityCard {

    public $security_card_name = 'admin_security_card';

    public static function getInstance() {
        static $obj;
        if (!$obj) {
            $obj = new self();
        }
        return $obj;
    }

    /**
     * 随机生成密保卡坐标
     *
     * @param
     * @return string
     */
    function shuffleLocation() {
        $str_x = '123456789';
        $str_y = 'ABCEDEGHI';
        $code = [];

        for ($i = 0; $i < 9; $i++) {
            for ($k = 0; $k < 9; $k++) {
                $code[] = $str_y[$i] . $str_x[$k];
            }
        }

        shuffle($code);
        $code = array_slice($code, 0, 2);
        return $code[0] . $code[1];
    }

    /**
     * 绑定密保卡
     *
     * @param $bind_user
     * @return int
     */
    function bindCard($bind_user) {
        $card_data = $this->makeSecurityCode();
        $is_bind = $this->checkBind($bind_user);

        if ($is_bind) {
            return true;
        } else {
            return ssdbService::getInstance()->hset($this->security_card_name, $bind_user, $card_data);
        }
    }

    /**
     * 更新密保卡
     *
     * @param string $bind_user
     * @return int
     */
    function updateCard($bind_user) {
        $card_data = self::makeSecurityCode();
        $is_bind = $this->checkBind($bind_user);

        if ($is_bind) {
            return ssdbService::getInstance()->hset($this->security_card_name, $bind_user, $card_data);
        } else {
            return false;
        }
    }

    /**
     * 取消绑定
     *
     * @param string $bind_user
     * @param bool $check_usc
     * @return bool
     */
    function unBind($bind_user) {
        $is_bind = $this->checkBind($bind_user);
        if ($is_bind) {
            ssdbService::getInstance()->hdel($this->security_card_name, $bind_user);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检查是否绑定过密保卡
     *
     * @param string $bind_user
     * @return bool
     */
    public function checkBind($bind_user) {
        return ssdbService::getInstance()->hexists($this->security_card_name, $bind_user);
    }

    /**
     * 返回密保卡数据
     *
     * @param $bind_user
     * @return int
     */
    function securityData($bind_user) {
        $is_bind = $this->checkBind($bind_user);
        if ($is_bind) {
            return $this->getSecurityData($bind_user);
        } else {
            return false;
        }
    }

    /**
     * 输出密保卡图片
     *
     * @param $bind_user
     * @return array|string|bool
     */
    function makeSecurityCardImage($bind_user) {
        $is_bind = $this->checkBind($bind_user);
        if (!$is_bind) {
            return false;
        }

        $data = $this->securityData($bind_user);
        if ($data == false) {
            return false;
        }

        $im = imagecreatetruecolor(520, 520);
        // 设置背景为白色
        imagefilledrectangle($im, 31, 31, 520, 520, 0xFFFFFF);

        $front = 5;
        $_space = 50;
        $_margin = 20;

        $_y = $_x = $_i = 0;
        if (is_array($data)) {
            $color = imagecolorallocate($im, 45, 45, 45);
            $color2 = imagecolorallocate($im, 205, 205, 205);

            imageline($im, $_x + 30, 0, $_x + 30, 480, $color);
            imageline($im, 0, 0, 0, 480, $color);

            imageline($im, 0, $_y + 30, 480, $_x + 30, $color);
            imageline($im, 0, 0, 480, 0, $color);

            foreach ($data as $y => $c) {
                ++$_i;

                imagestring($im, $front, $_margin - 10, $_y + $_space, $y, 0xFFBB00);
                imagestring($im, $front, $_x + $_space, $_margin - 10, $_i, 0xFFBB00);

                $code_location = 0;
                $_x = $_y += $_space;
                foreach ($c as $code_index => $code) {
                    if ($_i == $code_index) {
                        $char_color = 0x009933;
                    } else {
                        $char_color = 0x666666;
                    }

                    $code_location += $_space;
                    imagestring($im, $front, $code_location, $_y, $code, $char_color);
                }

                imageline($im, $_x + 30, 0, $_x + 30, 480, $color);
                imageline($im, 0, $_y + 30, 480, $_x + 30, $color);
            }

            imagestring($im, $front, 350, $_y + 46, "guipin.com", 0xCCCCCC);

            imageline($im, 519, 519, 500, 520, $color2);
            imageline($im, 519, 519, 520, 500, $color2);
        }

        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename=' . ucfirst($bind_user) . '_SecurityCard.png');
        imagepng($im);
        return true;
    }

    /**
     * 验证密保卡
     *
     * @param $user
     * @param $location
     * @param $input_code
     * @return bool|int
     */
    public function verifyCode($user, $location, $input_code) {
        $code_data = $this->getSecurityData($user);

        $right_code = $code_data[$location[0]][$location[1]] . $code_data[$location[2]][$location[3]];

        #判断是否相等
        if ($input_code == $right_code) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 取得密保卡数据
     *
     * @param string
     * @return array|bool
     */
    public function getSecurityData($bind_user) {
        $is_bind = $this->checkBind($bind_user);

        if ($is_bind) {
            $data = ssdbService::getInstance()->hget($this->security_card_name, $bind_user);
            return json_decode($data, true, JSON_BIGINT_AS_STRING, 512);
        }

        return false;
    }

    /**
     * 生成密保卡数据
     *
     * @param bool $is_serialize
     * @internal param $
     * @return array
     */
    private function makeSecurityCode($is_serialize = true) {
        $security = [];
        $str = '3456789ABCDEFGHJKMNPQRSTUVWXY';

        for ($k = 65; $k < 74; $k++) {
            for ($i = 1; $i <= 9; $i++) {
                $_x = substr(str_shuffle($str), $i, $i + 2);
                $security[chr($k)][$i] = $_x[0] . $_x[1];
            }
        }
        if ($is_serialize === true) {
            return json_encode($security);
        }
        return $security;
    }

}
