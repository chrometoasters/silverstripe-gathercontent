<?php

/**
 * Class SSGatherContentProcessor
 *
 * Class dedicated to hold various fields and values processing functions to allow for easy mapping between different
 * conventions used in GatherContent and php code
 *
 * All function should declare no parameters and access them via func_get_args(). Field or value's content/value
 * is passed in as the first parameter.
 *
 */
class SSGatherContentProcessor extends Object {

    /**
     * Remove given prefix from string
     *
     * Prefix must start at the very first position of the string.
     *
     * Passed in but accessed via func_get_args()
     * param $value
     * param string $prefix
     *
     * @return mixed
     */
    public static function removePrefix() {

        $args = func_get_args();
        $value = $args[0];
        $prefix = $args[1];

        if (stripos($value, $prefix) === 0) {
            $value = preg_replace('/' . preg_quote($prefix,'/') . '/i', '', $value, 1);
        }

        return $value;
    }


    /**
     * Convert string to camelCase with extra delimiters added
     *
     * Passed in but accessed via func_get_args()
     * param $value
     *
     * @return string
     */
    public static function camelCase() {
        $args = func_get_args();
        $value = $args[0];

        // if string, camelCase the value and remove word splitting characters
        if (is_string($value)) {
            $value = ucwords($value, " \t\r\n\f\v-_;");
            $value = str_replace(str_split(" \t\r\n\f\v-_;"), array(), $value);
        }

        return $value;
    }


    /**
     * If provided value is string, trim it, otherwise leave intact
     *
     * Passed in but accessed via func_get_args()
     * param $value
     *
     * @return string|mixed         trimmed string or original value
     */
    public static function trimString() {
        $args = func_get_args();
        $value = $args[0];

        if (is_string($value)) {
            // trim string
            $value = trim($value);

            // unicode whitespaces (https://stackoverflow.com/questions/4166896/trim-unicode-whitespace-in-php-5-2)
            $value = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $value);
        }

        return $value;
    }


    /**
     * If provided value is string, split it into an array using new line as a delimiter
     *
     * Passed in but accessed via func_get_args()
     * param $value
     *
     * @return array                array of rows or original value
     */
    public static function linesToArray() {
        $args = func_get_args();
        $value = $args[0];

        // only explode strings
        if (is_string($value)) {
            $value = explode("\n", $value);
        }

        return $value;
    }


    /**
     * If provided value is an array, return its first value
     *
     * Passed in but accessed via func_get_args()
     * param $value
     *
     * @return mixed                first value of array or original value
     */
    public static function firstFromArray() {
        $args = func_get_args();
        $value = $args[0];

        // only if we've got an array
        if (is_array($value)) {
            $value = $value[0];
        }

        return $value;
    }


}
