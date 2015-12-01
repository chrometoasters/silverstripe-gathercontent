<?php

/**
 * Class SSGatherContentAPI
 *
 * Implementation of functions directly communicating with GatherContent standard API,
 * see https://gathercontent.com/developers/ for details
 *
 */
class SSGatherContentAPIWrapper extends SSGatherContentGeneralAPIWrapper {

    /**
     * Request data from GatherContent using new API and return array containing response data and http code
     * as returned by curl
     *
     * If some parameters are provided via $params, the call uses POST instead of GET and the data is passed through.
     *
     * @param string $method        part of the url to be added to the API url to make specific calls
     * @param array $params         POST data to be passed through to the endpoint
     * @return array                array containing response and http code returned by curl
     */
    protected function callAPI($method = '', $params = []) {

        $url = $this->cfg['url'] . $method;
        $httpHeader = ['Accept: application/vnd.gathercontent.v0.5+json'];
        $userPwd = $this->cfg['username'] . ':' . $this->cfg['key'];

        return SSGatherContentTools::fetchAPI($url, $httpHeader, $userPwd, $params);

    }


    /**
     * Read and decode data from GatherContent using the API and return decoded associated array data
     *
     * @param string $method        part of the url to be added to the API url to make specific calls
     * @param array $params         POST data to be passed through to the endpoint
     * @return array|bool           false on error OR response data in an associative array
     */
    public function readAPI($method, $params = []) {

        $result = $this->callAPI($method, $params); // false or [code, response] array

        if ($result && ($result['code'] === 200)) {
            $response = json_decode($result['response'], true);

            if ($response['data']) {
                return $response['data'];
            }
        }

        return false; // default if no data or error, we don't distinguish on this level
    }

}