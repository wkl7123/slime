<?php
namespace Slime\Utils;

class Str
{
    /**
     * @param string $sTPL
     * @param array  $aReplaceMap
     *
     * @return string
     */
    public static function buildFromTPL($sTPL, $aReplaceMap)
    {
        $aReplace = [];
        foreach ($aReplaceMap as $sK => $mV) {
            $aReplace['{' . $sK . '}'] = (is_array($mV) || is_object($mV)) ? json_encode($mV) : (string)$mV;
        }

        return strtr($sTPL, $aReplace);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @param  string     $value
     * @param  int        $limit
     * @param  string     $end
     * @param null|string $nsEncoding
     *
     * @return string
     */
    public static function limit($value, $limit = 100, $end = '...', $nsEncoding = 'UTF-8')
    {
        $limit -= mb_strlen($end);
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit, $nsEncoding)) . $end;
    }

    /**
     * Generate a more truly "random" alpha-numeric string.
     *
     * @param  int $length
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function random($length = 16)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length * 2);

            if ($bytes === false) {
                throw new \RuntimeException('[Str] ; Unable to generate random string.');
            }

            return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
        }

        return static::quickRandom($length);
    }

    /**
     * Generate a "random" alpha-numeric string.
     *
     * Should not be considered sufficient for cryptography, etc.
     *
     * @param  int $length
     *
     * @return string
     */
    public static function quickRandom($length = 16)
    {
        static $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string $value
     * @param  string $delimiter
     *
     * @return string
     */
    public static function snake($value, $delimiter = '_')
    {
        if (ctype_lower($value)) {
            return $value;
        }

        $replace = '$1' . $delimiter . '$2';

        return strtolower(preg_replace('/(.)([A-Z])/', $replace, $value));
    }

    /**
     * Convert a string to camel style.
     *
     * @param string       $sStr
     * @param string|array $saDelimiter
     *
     * @return string
     */
    public static function camel($sStr, $saDelimiter = '_')
    {
        return ucfirst(str_replace(' ', '', ucwords(str_replace($saDelimiter, ' ', $sStr))));
    }
}
