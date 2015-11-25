<?php

/**
 * Class SSGatherContentProcessor
 *
 * Class dedicated to hold various fields and values processing functions to allow for easy mapping between different
 * conventions used in GatherContent and php code
 *
 */
class SSGatherContentProcessor extends Object {

    /**
     * Remove given prefix from string
     *
     * @param $field
     * @param string $prefix
     * @return mixed
     */
    public function removePrefix($field, $prefix = '') {

        if (strpos($field, $prefix) === 0) {
            $field = preg_replace('/' . preg_quote($prefix,'/') . '/', '', $field, 1);
        }

        return $field;
    }


}