<?php

/**
 * Class SSGatherContentTools
 *
 * Class holding various module tools like curl wrappers etc. to keep things at least a little bit separated
 *
 */
class SSGatherContentTools extends SS_Object {


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
    public static function fetchAPI($url, $httpHeader, $userPwd, $params = array(), $pluginAPI = false) {

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

            return array(
                'code' => (int)$httpCode,
                'response' => $response,
            );

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

            return array(
                'code' => (int)$httpCode,
                'response' => $response,
            );

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

        return array('folder' => $folder, 'filename' => $destFilename, 'fullPath' => $fullPath);
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
    public static function downloadFileIntoAssetsSubfolder($S3FileStoreUrl, $S3FileIdentifier, $assetsSubfolder, $filename, $overwriteFiles, $indexInTheCMS = true) {

        // get destination storage
        $store = self::getFolderAndUniqueFilename($assetsSubfolder, $filename, !$overwriteFiles);
        $folder = $store['folder'];

        // compose S3 url for download
        $S3Url = $S3FileStoreUrl . $S3FileIdentifier;

        // fetch the file
        $res = self::downloadFileFromS3($S3Url, $store['fullPath']);

        // if downloaded successfully, update CMS db and return ID of the file
        if ($res && $res['response'] && ($res['code'] === 200)) {
            if ($indexInTheCMS) {
                return $folder->constructChild($store['filename']);
            } else {
                return true;
            }
        } else {
            return false;
        }
    }


    /**
     * Download and back up file from GatherContent S3 storage, save it under given assets folder overwriting existing files, not indexing in the CMS
     * Wrapping function for downloadFileIntoAssetsSubfolder with some hardcoded parameters
     *
     * @param string $S3FileStoreUrl        GC S3 url with trailing slash
     * @param string $S3FileIdentifier      GC S3 file identifier obtained from the API call
     * @param string $assetsSubfolder       subfolder under assets folder where to store downloaded file
     * @param string $filename              original filename used when uploading to GC under which the file is stored (if overwriteFiles is false, could be unique variation)
     * @return bool                         successfully downloaded and stored?
     */
    public static function backupFileIntoAssetsSubfolder($S3FileStoreUrl, $S3FileIdentifier, $assetsSubfolder, $filename) {
        return self::downloadFileIntoAssetsSubfolder($S3FileStoreUrl, $S3FileIdentifier, $assetsSubfolder, $filename, true, false);
    }


    /**
     * Save given data into a file under assets subfolder, optionally creating unique file name when overwriting is disabled.
     * If the .json file extension is not part of the filename, it will be added.
     *
     * By default this function also index the file in the CMS and return its ID. For backup it's not necessary, so it
     * can be turned off.
     *
     * @param mixed $data                   data to be stored in the JSON file
     * @param string $assetsSubfolder       subfolder under assets folder where to store the file
     * @param string $filename              filename to be used
     * @param bool|true $overwriteFiles     overwrite already existing files? if false, unique filename is generated if file already exists
     * @param bool|true $indexInTheCMS      determine whether to index the file in the CMS and return value
     * @return bool|int|array               ID of File within the cms when indexed in the CMS OR 'store' array as returned
     *                                      from getFolderAndUniqueFilename OR false in case of failure
     */
    public static function saveDataInJSON($data, $assetsSubfolder, $filename, $overwriteFiles = true, $indexInTheCMS = true) {

        // check file extension
        if (substr($filename, -5) !== '.json') {
            $filename .= '.json';
        }

        // get destination storage
        $store = self::getFolderAndUniqueFilename($assetsSubfolder, $filename, !$overwriteFiles);
        $folder = $store['folder'];

        // if saved successfully, update CMS db and return ID of the file
        if (file_put_contents($store['fullPath'], json_encode($data))) {
            if ($indexInTheCMS) {
                return $folder->constructChild($store['filename']);
            } else {
                return $store;
            }
        } else {
            return false;
        }

    }


    /**
     * Back up given data into a file under assets subfolder. Wrapper for saveDataInJSON function for a specific use
     * by backup methods of the API
     * Hardcoded: files overwriting, not indexing files in the CMS
     *
     * @param mixed $data                   data to be backed up in a JSON file
     * @param string $assetsSubfolder       subfolder under assets folder where to store the file
     * @param string $filename              filename to be used
     * @return array|bool                   'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure or no data
     */
    public static function backupDataInJSON($data, $assetsSubfolder, $filename) {

        if ($data) {
            $store = self::saveDataInJSON($data, $assetsSubfolder, $filename, true, false);
            if ($store) {
                return array('data' => $data, 'store' => $store);
            }
        }

        return false;
    }


    /**
     * Join multiple strings into correct path, inspired by http://stackoverflow.com/a/15575293/2303501
     * Accepts variable number of string parts.
     *
     * @return string       joined path / separated without duplicated /
     */
    public static function joinPaths() {
        $paths = array();

        $args = func_get_args();

        if ((count($args) === 1) && (is_array($args[0]))) {
            $args = $args[0];
        }

        foreach ($args as $arg) {
            if ($arg !== '') {
                $paths[] = $arg;
            }
        }

        return preg_replace('#/+#', '/', join('/', $paths));
    }


    /**
     * Transform array of values into an array where indexes are defined by $keyKey variable
     * and values by $valueKey.
     *
     * Should only be used for arrays with given strict structure such as API returned data where all the items
     * have the same structure.
     *
     *
     * @param array $array                      array to "flatten"
     * @param string|null $keyKey               key from the above array's item to be used as index for the product array OR null to not transform the key
     * @param string|null $valueKey             key from the above array's item to define the values for the product array OR null to use whole item
     * @param boolean|false $transformToArrays  determine whether to create array of nested items - useful for arrays with duplicate keys coming from keyKey value
     * @param boolean|callable|false $keyCb     callback to be applied to a key before using it
     * @param boolean|callable|false $valueCb   callback to be applied to a value before outputting it
     * @return string                           "flattened" array
     */
    public static function transformArray($array, $keyKey = null, $valueKey = null, $transformToArrays = false, $keyCb = false, $valueCb = false) {

        if (is_array($array)) {
            foreach ($array as $key => $item) {

                $outputValue = ($valueKey ? $item[$valueKey] : $item);
                if ($valueCb && is_callable($valueCb)) {
                    $outputValue = call_user_func($valueCb, $outputValue);
                }

                if ($keyKey && is_array($item) && array_key_exists($keyKey, $item)) {

                    $outputKey = $item[$keyKey];
                    if ($keyCb && is_callable($keyCb)) {
                        $outputKey = call_user_func($keyCb, $outputKey);
                    }
                    if ($transformToArrays) {
                        if (!array_key_exists($outputKey, $array)) {
                            $array[$outputKey] = array();
                        }
                        $array[$outputKey][$key] = ($valueKey ? $item[$valueKey] : $item);
                    } else {
                        $array[$outputKey] = ($valueKey ? $item[$valueKey] : $item);
                    }
                    unset($array[$key]);

                } else {

                    if ($keyCb && is_callable($keyCb)) {
                        $outputKey = call_user_func($keyCb, $key);

                        $array[$outputKey] = $outputValue;
                        unset($array[$key]);
                    } else {
                        $array[$key] = $outputValue;
                    }

                }
            }
        }

        return $array;
    }


    /**
     * Function to call all passed in filters to transform the input value
     *
     * @param array $filters            array of callable functions to be applied to the input, optionally with params
     * @param mixed $input              input to be transformed
     * @return mixed                    transformed input after all the callable functions have been applied to it
     */
    public static function applyTransformationFilters($filters, $input) {

        // we expect non-empty array of callable
        if (is_array($filters) && !empty($filters)) {

            /// iterate over list of filters
            foreach ($filters as $filter_item) {
                // is it an array meaning we have some params?
                if (is_array($filter_item)) {
                    // split fn and params
                    $filter_item_args = reset($filter_item);
                    $filter_item_fn = key($filter_item);
                } else {
                    // no params
                    $filter_item_args = null;
                    $filter_item_fn = $filter_item;
                }

                // is the fn actually callable?
                if (is_callable($filter_item_fn)) {
                    // do we have any params?
                    if ($filter_item_args !== null) {
                        // call with params
                        $input = call_user_func($filter_item_fn, $input, $filter_item_args);
                    } else {
                        // call with no params
                        $input = call_user_func($filter_item_fn, $input);
                    }
                }
            }
        }

        // pass through transformed input
        return $input;
    }


    /**
     * Lookup CMS item of given class based on a value of given field
     *
     * Optionally, if the item is not found, it can be created, if it's a simple one, with the value assigned
     * to the specified field.
     *
     * @param string $field             name of the field to be used for lookup
     * @param mixed $value              value to be looked for
     * @param string $class             class of the item, defaulting to SiteTree for pages, can be a specific DO as well
     * @param bool|false $create        whether to create the item if it wasn't found
     * @return mixed|null               null when not found or failed creating, CMS item itself if found
     */
    public static function getItemByLookupField($field, $value, $class = 'SiteTree', $create = false, $publishCreated = false) {

        if (empty($field) || empty($value) || empty($class)) return false;

        $item = $class::get()->filter(array($field => $value));

        // if the item exists
        if ($item->exists()) {
            return $item->first();

        // if the item doesn't exist but we can create it - handy for simple DOs for example
        } elseif ($create) {

            try {
                $item = new $class();
                $item->$field = $value;
                if (is_subclass_of($class, 'SiteTree')) {
                    $item->ParentID = 0; // top level
                    $item->write();

                    if ($publishCreated) {
                        $item->doRestoreToStage();
                        $item->publish('Stage', 'Live');
                    }

                } else {
                    $item->write();
                }

                return $item;
            } catch (Exception $ex) {
                return null;
            }
        }

        return null;
    }


    /**
     * Lookup CMS item of given class based on GatherContent stored ID (field defined in the config)
     *
     * This function uses more generic SSGatherContentTools::getItemByLookupField() with some predefined parameters.
     *
     * @param string $uniqueIdentifier  SSGatherContentDataExtension primary ID's field name coming from the config
     * @param string $id                GatherContent ID
     * @param string $class             class of the item
     * @return mixed|null               CMS item itself OR null when not found
     */
    public static function getItemByGCUniqueIdentifier($uniqueIdentifier, $id, $class = 'SiteTree') {
        return SSGatherContentTools::getItemByLookupField($uniqueIdentifier, $id, $class, false);
    }


    /**
     * Compose full path from given assets folder/array of folders and filename
     *
     * @param string|array $path        folder or array of folder under assets
     * @param string $filename          filename or pattern to be added to the end
     * @return string                   full absolute path
     */
    public static function getComposedFullPath($path, $filename) {

        // if we've got array of paths, join them together
        if (is_array($path)) {
            $path = SSGatherContentTools::joinPaths($path);
        }

        // get folder object
        $folder = Folder::find_or_make($path);

        // compose full path
        $fullPath = SSGatherContentTools::joinPaths($folder->getFullPath(), $filename);

        return $fullPath;
    }


    /**
     * Load JSON data from a given file and decode it into an associative array
     *
     * If the .json file extension is not part of the filename, it will be added.
     *
     * @param string $filename              absolute path to the file with the filename
     * @return array|null                   associative decoded array OR null
     */
    public static function loadDataFromJSON($filename) {

        // read file if exits
        if (file_exists($filename)) {
            $json_encoded = file_get_contents($filename);

            // decode associative array
            if (!is_null($json_encoded)) {
                return json_decode($json_encoded, true);
            }
        }

        return null;
    }


    /**
     * Load JSON data from files based on a filename pattern, decode it and return in an associative array
     * of associative arrays
     *
     * If the .json file extension is not part of the filename pattern, it will be added.
     * This function uses glob() so not all pattern expressions are available, see the documentation.
     *
     * @param string $assetsSubfolder          assets subfolder within which we search for the files
     * @param string $filenamePattern          filename pattern
     * @return array                           array of loaded data
     */
    public static function loadDataFromJSONByPattern($assetsSubfolder, $filenamePattern) {

        $output = array();

        // add file extension to the pattern if missing
        if (substr($filenamePattern, -5) !== '.json') {
            $filenamePattern .= '.json';
        }

        // get files matching the pattern
        $sourceFiles = glob(self::getComposedFullPath($assetsSubfolder, $filenamePattern));

        // iterate over the files
        foreach ($sourceFiles as $sourceFile) {

            $jsonData = self::loadDataFromJSON($sourceFile);

            if ($jsonData && is_array($jsonData)) {
                $output[] = $jsonData;
            }
        }

        return $output;
    }


    /**
     * Get SOV (Size Of Variable) - memory used to hold the variable
     *
     * @param mixed $var                        variable to be examined
     * @param boolean|true $humanReadable        whether to return human readable or integer representation
     * @return int|string                       size in memory
     */
    public static function getSizeOfVariable($var, $humanReadable = true) {

        function bytesToSize($bytes, $precision = 2)
        {
            $kilobyte = 1024;
            $megabyte = $kilobyte * 1024;
            $gigabyte = $megabyte * 1024;
            $terabyte = $gigabyte * 1024;

            if (($bytes >= 0) && ($bytes < $kilobyte)) {
                return $bytes . ' B';

            } elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
                return round($bytes / $kilobyte, $precision) . ' KB';

            } elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
                return round($bytes / $megabyte, $precision) . ' MB';

            } elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
                return round($bytes / $gigabyte, $precision) . ' GB';

            } elseif ($bytes >= $terabyte) {
                return round($bytes / $terabyte, $precision) . ' TB';
            } else {
                return $bytes . ' B';
            }
        }

        $start_memory = memory_get_usage();
        $var = unserialize(serialize($var));
        $usage = (memory_get_usage() - $start_memory - PHP_INT_SIZE * 8);

        if ($humanReadable) {
            return bytesToSize($usage);
        }

        return $usage;
    }


}
