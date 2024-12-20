<?php
/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 21/11/2016
 * Time: 16:14
 */

namespace Boleto\Helper;


class Helper
{


    public static function number($str)
    {
        if(empty($str)){
            return $str;
        }

        return preg_replace("/[^0-9]/", "", $str);
    }

    public static function splitPhone($str): ?array
    {

        if (empty($str)) {
            return null;
        }

        $str = preg_replace("/[^0-9]/", "", $str);

        if ($str == '') {
            return null;
        }

        if (strlen($str) >= 12) {
            $str = substr($str, 2);
        }

        $ddd = strlen($str) >= 10 ? substr($str, 0, 2) : null;
        $numero = strlen($str) >= 10 ? substr($str, 2) : $str;

        if (in_array(substr($numero, 0, 1), [7, 8, 9])) {
            $tipo = 'Celular';
        } else {
            $tipo = 'Fixo';
        }

        return [
            'tipo' => $tipo,
            'ddd' => $ddd,
            'numero' => $numero
        ];

    }

    public static function padLeft($str, $length): string
    {
        return str_pad(self::number($str), $length, "0", STR_PAD_LEFT);
    }

    public static function utf8_converter($array)
    {
        array_walk_recursive($array, function (&$item, $key) {
            if (!mb_detect_encoding($item, 'UTF-8', true)) {
                $item = mb_convert_encoding($item, 'ISO-8859-1', 'UTF-8');
            }
        });

        return $array;
    }

    public static function floatVal($number): float
    {
        if (!is_numeric($number)) {
            $number = str_replace(['.', ','], ['', '.'], $number);
        }
        return floatval($number);
    }

    public static function intVal($number): int
    {
        if (!is_numeric($number)) {
            $number = str_replace(['.', ','], ['', '.'], $number);
        }
        return intval($number);
    }

    public static function numberFormat($number): ?string
    {
        if(empty($number)){
            return null;
        }

        if (!is_numeric($number)) {
            $number = str_replace(['.', ','], ['', '.'], $number);
        }
        return number_format($number, 2, '.', '');
    }

    public static function ascii($string): array|string|null
    {
        if (empty($string)) {
            return null;
        }
        return preg_replace('/[`^~\'"]/', '', str_replace('?', '', iconv('UTF-8', 'ASCII//TRANSLIT', $string)));
    }

    public static function substr($string, $start, $length = null): ?string
    {
        if (is_null($string)) {
            return null;
        }
        if (is_null($length)) {
            return mb_substr((string)$string, $start);
        }
        return mb_substr((string)$string, $start, $length);
    }

    static function mask($str, $mask)
    {
        $str = self::getNumber($str);

        if (empty($str)) {
            return $str;
        }

        $text = '';
        $k = 0;
        for ($i = 0; $i <= Helper::strlen($mask) - 1; $i++) {
            if ($mask[$i] == '#') {
                if (isset ($str[$k]))
                    $text .= $str[$k++];
            } else {
                if (isset ($mask[$i]))
                    $text .= $mask[$i];
            }
        }
        return $text;
    }

    static function getNumber($str): array|string|null
    {
        if (is_null($str)) {
            return null;
        }
        return preg_replace("/[^0-9]/", "", $str);
    }

    public static function strlen($str): int
    {
        if (is_null($str)) {
            return 0;
        }
        return strlen($str);
    }

    public static function alfaNumerico($str): string|null
    {
        if (is_null($str)) {
            return null;
        }
        return preg_replace('/[^a-zA-Z0-9 ]/', '', $str);
    }
}
