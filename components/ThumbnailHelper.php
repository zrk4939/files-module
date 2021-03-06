<?php
/**
 * Created by PhpStorm.
 * User: Илья
 * Date: 27.02.2017
 * Time: 15:16
 */

namespace zrk4939\modules\files\components;


use yii\base\BaseObject;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;

/**
 * Class ThumbnailHelper
 */
class ThumbnailHelper extends BaseObject
{
    /**
     * @param string $imagesDir
     * @param string $item
     * @param string $thumb_key
     * @param array $sizes
     */
    public static function createImageThumbnail(string $imagesDir, string $item, string $thumb_key, array $sizes)
    {
        $thumbFileName = $thumb_key . '_' . $item;
        $force = isset($sizes['force']) ? $sizes['force'] : false;

        if (!file_exists($imagesDir . $thumbFileName) || $force) {
            self::generateImageThumbnail(
                $imagesDir . $item,
                $imagesDir . $thumbFileName,
                $sizes['width'],
                $sizes['height'],
                $sizes['quality'],
                $sizes['cropAndCenter']
            );
        }
    }

    /**
     * @param $file string
     * @return boolean
     */
    public static function isImage($file)
    {
        $result = false;
        try {
            if (function_exists('exif_imagetype')) {
                $result = exif_imagetype($file);
            } else {
                $result = getimagesize($file);
            }
        } catch (\Exception $e) {
        }

        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * @param string $source_image_path
     * @param string $thumbnail_image_path
     * @param int $width
     * @param int $height
     * @param int $quality
     * @param bool $cropAndCenter
     * @return bool
     */
    protected static function generateImageThumbnail(string $source_image_path, string $thumbnail_image_path, int $width, int $height, int $quality, bool $cropAndCenter)
    {
        if (!static::isImage($source_image_path)) {
            return false;
        }
        // read image
        list($source_image_width, $source_image_height, $source_gd_image, $source_image_type) = self::readImage($source_image_path);
        if (empty($source_gd_image)) {
            return false;
        }

        /* ----- */

        if ($source_image_type === IMAGETYPE_JPEG) {
            try {
                $exif = exif_read_data($source_image_path);
            } catch (\Exception $exp) {
                $exif = false;
            }

            if ($exif && !empty($exif['Orientation'])) {
                $reSave = false;
                switch ($exif['Orientation']) {
                    case 8:
                        $source_gd_image = imagerotate($source_gd_image, 90, 0);
                        $reSave = true;
                        break;
                    case 3:
                        $source_gd_image = imagerotate($source_gd_image, 180, 0);
                        $reSave = true;
                        break;
                    case 6:
                        $source_gd_image = imagerotate($source_gd_image, -90, 0);
                        $reSave = true;
                        break;
                }

                if ($reSave) {
                    $result = imagejpeg($source_gd_image, $source_image_path, 95);
                    if ($result) {
                        chmod($source_image_path, 0775);
                    }
                    list($source_image_width, $source_image_height, $source_gd_image) = self::readImage($source_image_path);
                }
            }
        }

        if ($cropAndCenter) {
            $ratio_orig = $source_image_width / $source_image_height;

            if ($width / $height > $ratio_orig) {
                $new_height = $width / $ratio_orig;
                $new_width = $width;
            } else {
                $new_width = $height * $ratio_orig;
                $new_height = $height;
            }

            $x_mid = $new_width / 2;  //horizontal middle
            $y_mid = $new_height / 2; //vertical middle

            $process = imagecreatetruecolor(round($new_width), round($new_height));
            imagealphablending($process, false);
            imagesavealpha($process, true);
            imagecopyresampled($process, $source_gd_image, 0, 0, 0, 0, $new_width, $new_height, $source_image_width, $source_image_height);
            $thumbnail_gd_image = imagecreatetruecolor($width, $height);
            imagealphablending($thumbnail_gd_image, false);
            imagesavealpha($thumbnail_gd_image, true);
            imagecopyresampled($thumbnail_gd_image, $process, 0, 0, ($x_mid - ($width / 2)), ($y_mid - ($height / 2)), $width, $height, $width, $height);
        } else {
            $source_aspect_ratio = $source_image_width / $source_image_height;
            $thumbnail_aspect_ratio = $width / $height;

            if ($source_image_width <= $width && $source_image_height <= $height) {
                $thumbnail_image_width = $source_image_width;
                $thumbnail_image_height = $source_image_height;
            } elseif ($thumbnail_aspect_ratio > $source_aspect_ratio) {
                $thumbnail_image_width = (int)($height * $source_aspect_ratio);
                $thumbnail_image_height = $height;
            } else {
                $thumbnail_image_width = $width;
                $thumbnail_image_height = (int)($width / $source_aspect_ratio);
            }

            $thumbnail_gd_image = imagecreatetruecolor($thumbnail_image_width, $thumbnail_image_height);
            imagealphablending($thumbnail_gd_image, false);
            imagesavealpha($thumbnail_gd_image, true);
            imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, $thumbnail_image_width, $thumbnail_image_height, $source_image_width, $source_image_height);
        }

        if ($source_image_type === IMAGETYPE_GIF) {
            $result = imagegif($thumbnail_gd_image, $thumbnail_image_path);
        } else if ($source_image_type === IMAGETYPE_PNG) {
            $result = imagepng($thumbnail_gd_image, $thumbnail_image_path, 1);
        } else {
            $result = imagejpeg($thumbnail_gd_image, $thumbnail_image_path, $quality);
        }

        if ($result) {
            chmod($thumbnail_image_path, 0775);
        }
        imagedestroy($source_gd_image);
        imagedestroy($thumbnail_gd_image);
        return true;
    }

    /**
     * @param string $source_image_path
     * @return array
     */
    protected static function readImage($source_image_path)
    {
        list($source_image_width, $source_image_height, $source_image_type) = getimagesize($source_image_path);
        $source_gd_image = null;

        switch ($source_image_type) {
            case IMAGETYPE_JPEG:
                $source_gd_image = imagecreatefromjpeg($source_image_path);
                break;
            case IMAGETYPE_GIF:
                $source_gd_image = imagecreatefromgif($source_image_path);
                break;
            case IMAGETYPE_PNG:
                $source_gd_image = imagecreatefrompng($source_image_path);
                break;
        }

        return [$source_image_width, $source_image_height, $source_gd_image, $source_image_type];
    }
}
