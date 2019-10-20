<?php

namespace uCMS\Helpers;


/**
 * String helper functions.
 */
class Strings
{
    /**
     * Check whether given string starts with given prefix.
     * @param string $str String to be searched
     * @param string $prefix Prefix we are looking for
     * @return bool
     */
    public static function startsWith(string $str, string $prefix): bool
    {
        if ($prefix === '' || strlen($str) < strlen($prefix)) { // avoid bad situations
            return false;
        }
        return substr($str, 0, strlen($prefix)) === $prefix;
    }


    /**
     * Remove given prefix from given string. If the prefix is not present, original string is returned.
     * @param string $str String to be searched and cropped
     * @param string $prefix Prefix we are looking for
     * @return string
     */
    public static function removePrefix(string $str, string $prefix): string
    {
        if (self::startsWith($str, $prefix)) {
            return substr($str, strlen($prefix));
        }
        return $str;
    }
}
