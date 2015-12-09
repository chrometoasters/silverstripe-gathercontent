<?php

/**
 * Class SSGatherContentPluginAPI
 *
 * Implementation of functions directly communicating with GatherContent legacy developer API,
 * see https://gathercontent.com/support/developer-api/ for details
 *
 */
class SSGatherContentPluginAPIWrapper extends SSGatherContentGeneralAPIWrapper {

    /**
     * Request data from GatherContent using legacy plugin API and return array containing response data and http code
     * as returned by curl
     *
     * If some parameters are provided via $params, the call uses POST instead of GET and the data is passed through
     *
     * @param string $method        part of the url to be added to the API url to make specific calls (endpoint)
     * @param array $params         POST data to be passed through to the endpoint
     * @return array                array containing response and http code returned by curl
     */
    protected function callAPI($method = '', $params = array()) {

        $url = $this->cfg['url'] . $method;
        $httpHeader = array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded');
        $userPwd = $this->cfg['key'] . ":" . $this->cfg['password'];

        return SSGatherContentTools::fetchAPI($url, $httpHeader, $userPwd, $params, 'isPluginApi');

    }


    /**
     * Read and decode data from GatherContent using the API and return decoded associated array data
     *
     * @param string $method        part of the url to be added to the API url to make specific calls
     * @param array $params         POST data to be passed through to the endpoint
     * @param string $returnKey     key to a value from the response array to be returned instead of whole array
     * @return array|bool           false on error OR response data in an associative array
     */
    public function readAPI($method, $params = array(), $returnKey = '') {

        $result = $this->callAPI($method, $params); // false or [code, response] array

        if ($result && ($result['code'] === 200)) {
            $response = json_decode($result['response'], true);

            if ($response['success']) {
                if ($returnKey) {
                    return $response[$returnKey];
                } else {
                    unset($response['success']);
                    return $response;
                }
            }
        }

        return false; // default if no data or error, we don't distinguish on this level
    }


}