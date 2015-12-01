<?php

/**
 * Class SSGatherContentGeneralAPIWrapper
 *
 * Wrapper for GatherContent APIs,
 * see https://gathercontent.com/developers/ and https://gathercontent.com/support/developer-api/ for details
 *
 * Standard API and legacy plugin API classes should extend this class and implement the callAPI method
 *
 */
abstract class SSGatherContentGeneralAPIWrapper {

    /**
     * API configuration
     *
     * @var array
     */
    protected $cfg;


    /**
     * Class constructor
     *
     * @param $cfg
     */
    public function __construct($cfg) {
        $this->cfg = $cfg;
    }


    /**
     * Request data from GatherContent
     *
     * @param string $method        API method
     * @param array $params         POST data
     */
    abstract protected function callAPI($method = '', $params = []);


}