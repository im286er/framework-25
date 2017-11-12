<?php

/**
 * kv图像操作类库
 */
class kvimage {

    /**
     * 生成缩略图
     * @static
     * @access public
     * @param string $image  原图
     * @param string $thumbname 缩略图文件名
     * @param string $maxWidth  宽度
     * @param string $maxHeight  高度
     * @return void
     */
    static function thumb($image, $thumbname, $maxWidth = 200, $maxHeight = 50) {
        // 获取原图信息
        $info = kvstoreModel::getInstance()->get_info($image);
        if ($info !== false) {
            $srcWidth = $info['width'];
            $srcHeight = $info['height'];
            $type = empty($type) ? $info['type'] : $type;
            $type = strtolower($type);
            if (!in_array($type, ['gif', 'png', 'jpg', 'jpeg'])) {
                return false;
            }
            $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight); // 计算缩放比例
            if ($scale >= 1) {
                // 超过原图大小不再缩略
                $width = $srcWidth;
                $height = $srcHeight;
            } else {
                // 缩略图尺寸
                $width = (int) ($srcWidth * $scale);
                $height = (int) ($srcHeight * $scale);
            }

            // 载入原图
            $data = kvstoreModel::getInstance()->get_data($image);
            if ($data == false) {
                return false;
            }
            $srcImg = imagecreatefromstring($data);
            if ($srcImg === false) {
                /* 图片内容格式杀错误 */
                return false;
            }
            unset($data);

            //创建缩略图
            if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
                $thumbImg = imagecreatetruecolor($width, $height);
            } else {
                $thumbImg = imagecreate($width, $height);
            }

            //png和gif的透明处理 by luofei614
            if ('png' == $type) {
                imagealphablending($thumbImg, false); //取消默认的混色模式（为解决阴影为绿色的问题）
                imagesavealpha($thumbImg, true); //设定保存完整的 alpha 通道信息（为解决阴影为绿色的问题）
            } elseif ('gif' == $type) {
                $trnprt_indx = imagecolortransparent($srcImg);
                if ($trnprt_indx >= 0) {
                    //its transparent
                    $trnprt_color = imagecolorsforindex($srcImg, $trnprt_indx);
                    $trnprt_indx = imagecolorallocate($thumbImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                    imagefill($thumbImg, 0, 0, $trnprt_indx);
                    imagecolortransparent($thumbImg, $trnprt_indx);
                }
            }
            // 复制图片
            if (function_exists("ImageCopyResampled")) {
                imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
            } else {
                imagecopyresized($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
            }

            // 对jpeg图形设置隔行扫描
            if ('jpg' == $type || 'jpeg' == $type) {
                imageinterlace($thumbImg, 1);
            }

            // 生成图片
            $imageFun = 'image' . ($type == 'jpg' ? 'jpeg' : $type);

            //保存图像
            if ($thumbname) {
                if ('jpg' == $type || 'jpeg' == $type) {
                    $imageFun($thumbImg, $thumbname, 90);
                } else {
                    $imageFun($thumbImg, $thumbname);
                }
            } else {
                // 直接显示
                Header("Content-type: " . $info['mime']);
                $imageFun($thumbImg);
            }
            imagedestroy($thumbImg);
            imagedestroy($srcImg);
            return $thumbname;
        }
        return false;
    }

    /**
     * 生成缩略图并加水印
     * @param type $image 原文件
     * @param string $savename 缩略图文件名
     * @param string $maxWidth  宽度
     * @param string $maxHeight  高度
     * @param type $water 水印图片
     * @return void
     */
    static public function thumb_water($image, $savename, $maxWidth = 200, $maxHeight = 50, $water, $alpha = 80) {
        //检查文件是否存在
        if (!file_exists($water)) {
            return false;
        }
        // 获取原图信息
        $info = kvstoreModel::getInstance()->get_info($image);
        if ($info === false) {
            return false;
        }
        $srcWidth = $info['width'];
        $srcHeight = $info['height'];
        $type = empty($type) ? $info['type'] : $type;
        $type = strtolower($type);
        $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight); // 计算缩放比例
        if ($scale >= 1) {
            // 超过原图大小不再缩略
            $width = $srcWidth;
            $height = $srcHeight;
        } else {
            // 缩略图尺寸
            $width = (int) ($srcWidth * $scale);
            $height = (int) ($srcHeight * $scale);
        }

        // 载入原图
        $data = kvstoreModel::getInstance()->get_data($image);
        if ($data == false) {
            return false;
        }
        $srcImg = imagecreatefromstring($data);
        if ($srcImg === false) {
            /* 图片内容格式杀错误 */
            return false;
        }
        unset($data);

        //创建缩略图
        if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
            $thumbImg = imagecreatetruecolor($width, $height);
        } else {
            $thumbImg = imagecreate($width, $height);
        }

        //png和gif的透明处理 by luofei614
        if ('png' == $type) {
            imagealphablending($thumbImg, false); //取消默认的混色模式（为解决阴影为绿色的问题）
            imagesavealpha($thumbImg, true); //设定保存完整的 alpha 通道信息（为解决阴影为绿色的问题）
        } elseif ('gif' == $type) {
            $trnprt_indx = imagecolortransparent($srcImg);
            if ($trnprt_indx >= 0) {
                //its transparent
                $trnprt_color = imagecolorsforindex($srcImg, $trnprt_indx);
                $trnprt_indx = imagecolorallocate($thumbImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                imagefill($thumbImg, 0, 0, $trnprt_indx);
                imagecolortransparent($thumbImg, $trnprt_indx);
            }
        }
        // 复制图片
        if (function_exists("ImageCopyResampled")) {
            imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
        } else {
            imagecopyresized($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
        }

        // 对jpeg图形设置隔行扫描
        if ('jpg' == $type || 'jpeg' == $type) {
            imageinterlace($thumbImg, 1);
        }

        //  销毁
        imagedestroy($srcImg);


        //图片信息
        $sInfo = [
            "width" => $width,
            "height" => $height,
            "type" => $type,
            "mime" => $info['mime']
        ];
        unset($info);
        $wInfo = kvstoreModel::getInstance()->getImageInfo($water);

        //如果图片小于水印图片，不生成图片
        if ($sInfo["width"] < $wInfo["width"] || $sInfo['height'] < $wInfo['height']) {
            //输出图像
            $ImageFun = 'Image' . $sInfo['type'];
            // 直接显示
            Header("Content-type: " . $sInfo['mime']);
            //保存图像
            $ImageFun($thumbImg, $savename);
            imagedestroy($thumbImg);
        } else {
            //水印图像位置
            $posX = rand(0, ($sInfo["width"] - $wInfo["width"]));
            $posY = rand(0, ($sInfo["height"] - $wInfo["height"]));

            //建立图像
            $wCreateFun = "imagecreatefrom" . $wInfo['type'];
            $wImage = $wCreateFun($water);

            //设定图像的混色模式
            imagealphablending($wImage, true);

            //生成混合图像
            imagecopymerge($thumbImg, $wImage, $posX, $posY, 0, 0, $wInfo['width'], $wInfo['height'], $alpha);
            //输出图像
            $ImageFun = 'Image' . $sInfo['type'];
            //保存图像
            if ($savename) {
                $ImageFun($thumbImg, $savename);
            } else {
                // 直接显示
                Header("Content-type: " . $sInfo['mime']);
                $ImageFun($thumbImg);
            }
            imagedestroy($thumbImg);
        }
    }

    /**
     * 生成缩略图并进行圆角处理,　主要用于头像处理
     * @static
     * @access public
     * @param string $image  原图
     * @param string $thumbname 缩略图文件名
     * @param string $maxWidth  宽度
     * @param string $maxHeight  高度
     * @param type $radius      圆角半径
     * @return void
     */
    public static function thumb_radius($image, $thumbname, $maxWidth = 200, $maxHeight = 50, $radius = 15) {
        // 获取原图信息
        $info = kvstoreModel::getInstance()->get_info($image);
        if ($info !== false) {
            $srcWidth = $info['width'];
            $srcHeight = $info['height'];
            $type = empty($type) ? $info['type'] : $type;
            $type = strtolower($type);
            if (!in_array($type, ['gif', 'png', 'jpg', 'jpeg'])) {
                return false;
            }
            $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight); // 计算缩放比例
            if ($scale >= 1) {
                // 超过原图大小不再缩略
                $width = $srcWidth;
                $height = $srcHeight;
            } else {
                // 缩略图尺寸
                $width = (int) ($srcWidth * $scale);
                $height = (int) ($srcHeight * $scale);
            }

            // 载入原图
            $data = kvstoreModel::getInstance()->get_data($image);
            if ($data == false) {
                return false;
            }
            $srcImg = imagecreatefromstring($data);
            if ($srcImg === false) {
                /* 图片内容格式杀错误 */
                return false;
            }
            unset($data);

            //创建缩略图
            $thumb_type = 'png';
            if ($thumb_type != 'gif' && function_exists('imagecreatetruecolor')) {
                $thumbImg = imagecreatetruecolor($width, $height);
            } else {
                $thumbImg = imagecreate($width, $height);
            }

            imagealphablending($thumbImg, false); //取消默认的混色模式（为解决阴影为绿色的问题）
            imagesavealpha($thumbImg, true); //设定保存完整的 alpha 通道信息（为解决阴影为绿色的问题）
            // 复制图片
            if (function_exists("ImageCopyResampled")) {
                imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
            } else {
                imagecopyresized($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
            }

            /* 圆角处理 */
            $radius = $radius == 0 ? (min($maxWidth, $maxHeight) / 2) : $radius;
            $img = imagecreatetruecolor($maxWidth, $maxHeight);
            //这一句一定要有
            imagesavealpha($img, true);
            //拾取一个完全透明的颜色,最后一个参数127为全透明
            $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
            imagefill($img, 0, 0, $bg);
            $r = $radius; //圆 角半径
            for ($x = 0; $x < $maxWidth; $x++) {
                for ($y = 0; $y < $maxHeight; $y++) {
                    $rgbColor = imagecolorat($thumbImg, $x, $y);
                    if (($x >= $radius && $x <= ($maxWidth - $radius)) || ($y >= $radius && $y <= ($maxHeight - $radius))) {
                        //不在四角的范围内,直接画
                        imagesetpixel($img, $x, $y, $rgbColor);
                    } else {
                        //在四角的范围内选择画
                        //上左
                        $y_x = $r; //圆心X坐标
                        $y_y = $r; //圆心Y坐标
                        if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                            imagesetpixel($img, $x, $y, $rgbColor);
                        }
                        //上右
                        $y_x = $maxWidth - $r; //圆心X坐标
                        $y_y = $r; //圆心Y坐标
                        if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                            imagesetpixel($img, $x, $y, $rgbColor);
                        }
                        //下左
                        $y_x = $r; //圆心X坐标
                        $y_y = $maxHeight - $r; //圆心Y坐标
                        if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                            imagesetpixel($img, $x, $y, $rgbColor);
                        }
                        //下右
                        $y_x = $maxWidth - $r; //圆心X坐标
                        $y_y = $maxHeight - $r; //圆心Y坐标
                        if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                            imagesetpixel($img, $x, $y, $rgbColor);
                        }
                    }
                }
            }
            $thumbImg = $img;
            unset($img);

            //保存图像
            if ($thumbname) {
                imagepng($thumbImg, $thumbname);
            } else {
                // 直接显示
                Header("Content-type: " . $info['mime']);
                imagepng($thumbImg);
            }

            imagedestroy($thumbImg);
            imagedestroy($srcImg);

            return $thumbname;
        }
        return false;
    }

    /**
     * 显示图片
     * @param type $imgFile
     * @return type
     */
    static public function showPic($imgFile) {
        //获取图像文件信息
        $info = kvstoreModel::getInstance()->get_info($imgFile);

        if ($info !== false) {
            // 载入原图
            $data = kvstoreModel::getInstance()->get_data($imgFile);
            if ($data) {
                Header("Content-type: " . $info['mime']);
                if ($info['type'] == 'gif') {
                    echo $data;
                    return;
                }
                $im = imagecreatefromstring($data);
                unset($data);
                $ImageFun = str_replace('/', '', $info['mime']);
                if ($info['type'] == 'png') {
                    imagealphablending($im, FALSE); //取消默认的混色模式
                    imagesavealpha($im, TRUE); //设定保存完整的 alpha 通道信息
                }
                if (('jpg' == $info['type']) || ('jpeg' == $info['type'])) {
                    /* 对jpeg图形设置隔行扫描 */
                    imageinterlace($im, 1);
                    /* 调显示质量 */
                    $ImageFun($im, NULL, 95);
                } else {
                    $ImageFun($im);
                }
                ImageDestroy($im);
                return;
            }
            return;
        }
    }

}
