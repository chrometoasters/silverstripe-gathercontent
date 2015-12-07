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


}