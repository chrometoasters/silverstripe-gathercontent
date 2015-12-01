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


    /**
     * Find unique file name by adding _1, _2 etc. if input or previous generated filename exists
     *
     * @param string $path              absolute file path with trailing slash
     * @param string $filename          filename to be checked
     * @return string                   unique filename within given path
     */
    private static function findUniqueFilename($path, $filename) {
        $i = 1;
        $path_parts = pathinfo($path . $filename);
        $ext = $path_parts['extension'];
        $fname = $path_parts['filename'];

        while (file_exists($path . $filename)) {
            $filename = $fname . "_$i.$ext";
            $i++;
        }
        return $filename;
    }


    /**
     * Create given destination folder for a potential file, optionally generating unique filename
     * and return all details in an associative array
     *
     * @param string $path              absolute file path with trailing slash
     * @param string $filename          filename to be created/checked
     * @param bool $createUnique        determine whether to create/check unique filename or not
     * @return array                    path and file details
     */
    private static function getFolderAndUniqueFilename($path, $filename, $createUnique) {
        // get or create destination folder under assets folder
        $folder = Folder::find_or_make($path);

        // determine final file name, overwrite by default
        $destFilename = $filename;
        if ($createUnique) {
            $destFilename = self::findUniqueFilename($folder->getFullPath(), $filename);
        }

        // compose full absolute path
        $fullPath = $folder->getFullPath() . $destFilename;

        return ['folder' => $folder, 'filename' => $destFilename, 'fullPath' => $fullPath];
    }


    /**
     * Download file from GatherContent S3 storage, save it under given assets folder (either overwriting existing or creating unique filename)
     * and in case of success, generate File object in the CMS and return its ID
     *
     * @param string $S3FileStoreUrl        GC S3 url with trailing slash
     * @param string $S3FileIdentifier      GC S3 file identifier obtained from the API call
     * @param string $assetsSubfolder       subfolder under assets folder where to store downloaded file
     * @param string $filename              original filename used when uploading to GC under which the file is stored (if overwriteFiles is false, could be unique variation)
     * @param bool $overwriteFiles          overwrite already existing files? if false, unique filename is generated if file already exists
     * @return bool|int                     ID of File within the cms OR false in case of failure
     */
    public static function downloadFileIntoAssetsFolder($S3FileStoreUrl, $S3FileIdentifier, $assetsSubfolder, $filename, $overwriteFiles) {

        // get destination storage
        $store = self::getFolderAndUniqueFilename($assetsSubfolder, $filename, !$overwriteFiles);
        $folder = $store['folder'];

        // compose S3 url for download
        $S3Url = $S3FileStoreUrl . $S3FileIdentifier;

        // fetch the file
        $res = self::downloadFileFromS3($S3Url, $store['fullPath']);

        // if downloaded successfully, update CMS db and return ID of the file
        if ($res && $res['response'] && ($res['code'] === 200)) {
            return $folder->constructChild($store['filename']);
        } else {
            return false;
        }
    }


    /**
     * Save given data into a file under assets subfolder, optionally creating unique file name when overwriting is disabled.
     * If the .json file extension is not part of the filename, it will be added.
     *
     * @param mixed $data                   data to be stored in the JSON file
     * @param string $assetsSubfolder       subfolder under assets folder where to store the file
     * @param string $filename              filename to be used
     * @param bool|true $overwriteFiles     overwrite already existing files? if false, unique filename is generated if file already exists
     * @return bool|int                     ID of File within the cms OR false in case of failure
     */
    public static function saveDataInJSON($data, $assetsSubfolder, $filename, $overwriteFiles = true) {

        // check file extension
        if (substr($filename, -5) !== '.json') {
            $filename .= '.json';
        }

        // get destination storage
        $store = self::getFolderAndUniqueFilename($assetsSubfolder, $filename, !$overwriteFiles);
        $folder = $store['folder'];

        // if saved successfully, update CMS db and return ID of the file
        if (file_put_contents($store['fullPath'], json_encode($data))) {
            return $folder->constructChild($store['filename']);
        } else {
            return false;
        }

    }

}