<?php

/**
 * Class SSGatherContentTools
 *
 * Class holding various module tools like curl wrappers etc. to keep things at least a little bit separated
 *
 */
class SSGatherContentTools extends Object {


    /**
     * Generic function to wrap API calls to both GatherContent APIs (standard or plugin API) and return an array
     * containing response data and http code as returned by curl_exec and curl_getinfo
     *
     * If some parameters are provided via $params, the call uses POST instead of GET and the data is passed through.
     *
     *
     * @param string $url           url to be queried
     * @param array $httpHeader     additional http header, here mostly used to specify accepted encoding
     * @param string $userPwd       authentication details
     * @param array $params         POST data to be passed through to the endpoint
     * @return array                array containing response and http code returned by curl
     */
    public static function fetchAPI($url, $httpHeader, $userPwd, $params = [], $pluginAPI = false) {

        try {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
            if (substr($url, 0, 8) == 'https://') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            }
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }

            if ($pluginAPI) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            } else {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'code' => (int)$httpCode,
                'response' => $response,
            ];

        } catch (Exception $e) {
            return false;
        }

    }


    /**
     * Download file from GatherContent and store it under given filename (including full absolute path)
     *
     * @param string $S3Url             url to be downloaded
     * @param string $assetsFilename    absolute path to a file
     * @return array|bool               array containing response and http code returned by curl
     */
    public static function downloadFileFromS3($S3Url, $assetsFilename) {

        try {

            set_time_limit(0);
            $fp = fopen($assetsFilename, 'w');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $S3Url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            if (substr($S3Url, 0, 8) == 'https://') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            fclose($fp);

            return [
                'code' => (int)$httpCode,
                'response' => $response,
            ];

        } catch (Exception $e) {
            return false;
        }

    }

}