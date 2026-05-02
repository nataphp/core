<?php
/**
 * NataPHP Framework
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        de77
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @see           http://de77.com/php/read-and-write-bmp-in-php-imagecreatefrombmp-imagebmp
 */

namespace Nata\Filesystem\File\Image\Processor\GD;

/**
 * Read 1,4,8,24,32bit BMP files
 * Save 24bit BMP files
 */

class BMP {

/**
 * Creates a BMP file from the given image.
 *
 * @param resource $img An image resource
 * @param string $filename Filename
 * @return
 */
    public static function imagebmp(&$img, $filename = false) {
        $wid = imagesx($img);
        $hei = imagesy($img);
        $wid_pad = str_pad('', $wid % 4, "\0");

        $size = 54 + ($wid + $wid_pad) * $hei * 3; //fixed

        // Prepare & save header
        $header['identifier'] = 'BM';
        $header['file_size'] = static::dword($size);
        $header['reserved'] = static::dword(0);
        $header['bitmap_data'] = static::dword(54);
        $header['header_size'] = static::dword(40);
        $header['width'] = static::dword($wid);
        $header['height'] = static::dword($hei);
        $header['planes'] = static::word(1);
        $header['bits_per_pixel'] = static::word(24);
        $header['compression'] = static::dword(0);
        $header['data_size'] = static::dword(0);
        $header['h_resolution'] = static::dword(0);
        $header['v_resolution'] = static::dword(0);
        $header['colors'] = static::dword(0);
        $header['important_colors'] = static::dword(0);

        if ($filename) {
            $f = fopen($filename, "wb");
            foreach ($header as $h) {
                fwrite($f, $h);
            }

            // Save pixels
            for ($y = ($hei - 1); $y >= 0; $y--) {
                for ($x = 0; $x < $wid; $x++) {
                    $rgb = imagecolorat($img, $x, $y);
                    fwrite($f, static::byte3($rgb));
                }
                fwrite($f, $wid_pad);
            }
            fclose($f);
        } else {
            foreach ($header as $h) {
                echo $h;
            }

            // Save pixels
            for ($y = ($hei - 1); $y >= 0; $y--) {
                for ($x = 0; $x < $wid; $x++) {
                    $rgb = imagecolorat($img, $x, $y);
                    echo static::byte3($rgb);
                }
                echo $wid_pad;
            }

        }

    }

/**
 * Create a new image from file or URL.
 *
 * @param string $filename Path to the BMP image.
 * @return resource Returns an image resource identifier on success, FALSE on errors.
 */
    public static function imagecreatefrombmp($filename) {
        $f = fopen($filename, "rb");

        // Read header
        $header = fread($f, 54);
        $header = unpack('c2identifier/Vfile_size/Vreserved/Vbitmap_data/Vheader_size/' .
            'Vwidth/Vheight/vplanes/vbits_per_pixel/Vcompression/Vdata_size/'.
            'Vh_resolution/Vv_resolution/Vcolors/Vimportant_colors', $header);

        if ($header['identifier1'] != 66 or $header['identifier2'] != 77) {
            die('Not a valid bmp file');
        }

        if (!in_array($header['bits_per_pixel'], [24, 32, 8, 4, 1])) {
            die('Only 1, 4, 8, 24 and 32 bit BMP images are supported');
        }

        $bps = $header['bits_per_pixel']; // Bits per pixel
        $wid2 = ceil(($bps / 8 * $header['width']) / 4) * 4;
        $colors = pow(2, $bps);

        $wid = $header['width'];
        $hei = $header['height'];

        $img = imagecreatetruecolor($header['width'], $header['height']);

        // Read palette
        if ($bps < 9) {
            for ($i = 0; $i<$colors; $i++) {
                $palette[] = static::undword(fread($f, 4));
            }
        } else {
            if ($bps == 32) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
            $palette = [];
        }

        // Read pixels
        for ($y = ($hei - 1); $y >= 0; $y--) {
            $row = fread($f, $wid2);
            $pixels = static::str_split2($row, $bps, $palette);

            for ($x = 0; $x < $wid; $x++) {
                static::makepixel($img, $x, $y, $pixels[$x], $bps);
            }
        }
        fclose($f);

        return $img;
    }

/**
 * str_split2.
 */
    private static function str_split2($row, $bps, $palette) {
        switch ($bps) {
            case 32:
            case 24:
                return str_split($row, $bps / 8);
            case 8:
                $out = [];
                $count = strlen($row);
                for ($i = 0; $i < $count; $i++) {
                    $out[] = $palette[ord($row[$i])];
                }
                return $out;
            case 4:
                $out = [];
                $count = strlen($row);
                for ($i = 0; $i < $count; $i++) {
                    $roww = ord($row[$i]);
                    $out[] = $palette[($roww & 240) >> 4];
                    $out[] = $palette[($roww & 15)];
                }
                return $out;
            case 1:
                $out = [];
                $count = strlen($row);
                for ($i = 0; $i < $count; $i++) {
                    $roww = ord($row[$i]);
                    $out[] = $palette[($roww & 128) >> 7];
                    $out[] = $palette[($roww & 64) >> 6];
                    $out[] = $palette[($roww & 32) >> 5];
                    $out[] = $palette[($roww & 16) >> 4];
                    $out[] = $palette[($roww & 8) >> 3];
                    $out[] = $palette[($roww & 4) >> 2];
                    $out[] = $palette[($roww & 2) >> 1];
                    $out[] = $palette[($roww & 1)];
                }
                return $out;
        }
    }

/**
 * str_split2.
 */
    private static function makepixel($img, $x, $y, $str, $bps) {
        switch ($bps) {
            case 32:
                $a = ord($str[0]);
                $b = ord($str[1]);
                $c = ord($str[2]);
                $d = 256 - ord($str[3]); // TODO: gives imperfect results
                $pixel = $d*256*256*256 + $c*256*256 + $b*256 + $a;
                imagesetpixel($img, $x, $y, $pixel);
                break;
            case 24:
                $a = ord($str[0]);
                $b = ord($str[1]);
                $c = ord($str[2]);
                $pixel = $c*256*256 + $b*256 + $a;
                imagesetpixel($img, $x, $y, $pixel);
                break;
            case 8:
            case 4:
            case 1:
                imagesetpixel($img, $x, $y, $str);
                break;
        }
    }

/**
 * byte3.
 */
    private static function byte3($n) {
        return chr($n & 255) . chr(($n >> 8) & 255) . chr(($n >> 16) & 255);
    }

/**
 * undword.
 */
    private static function undword($n) {
        $r = unpack("V", $n);
        return $r[1];
    }

/**
 * dword.
 */
    private static function dword($n) {
        return pack("V", $n);
    }

/**
 * word.
 */
    private static function word($n) {
        return pack("v", $n);
    }

}

function imagebmp(&$img, $filename = false) {
    return BMP::imagebmp($img, $filename);
}

function imagecreatefrombmp($filename) {
    return BMP::imagecreatefrombmp($filename);
}
