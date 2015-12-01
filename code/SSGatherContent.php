<?php

/**
 * Class SSGatherContent
 *
 * Main silverstripe-gathercontent module class where all the important magic is happening :-)
 *
 */
class SSGatherContent extends Object {

    /**
     * GatherContent API details
     * - url
     * - username
     * - key
     *
     * @config
     */
    private static $api = [];


    /**
     * GatherContent Plugin API details
     * - url where %%ACCOUNTNAME%% will be replaced by accountname
     * - accountname
     * - key
     * - password
     *
     * @config
     */
    private static $plugin_api = [];


    /**
     * GatherContent project name
     *
     * @config
     */
    private static $project = '';


    /**
     * GatherContent S3 file store URL
     *
     * @var string
     * @config
     */
    private static $s3_file_store_url;


    /**
     * Folder under CMS assets folder used to store downloaded files
     *
     * @var string
     * @config
     */
    private static $assets_subfolder = '';


    /**
     * Assets folder used to store downloaded JSON data
     * if $save_json_files is true
     *
     * @config
     */
    private static $assets_subfolder_json = '';


    /**
     * Determine whether JSON data is saved when downloaded from GatherContent
     *
     * @config
     */
    private static $save_json_files;


    /**
     * Determine whether to use JSON data files for items no longer referenced
     * by GatherContent, but possibly still valid for the project
     *
     * @config
     */
    private static $use_saved_json_files;


    /**
     * Determine whether to overwrite existing CMS items by items from GatherContent,
     * based on GatherContent unique item id
     *
     * @config
     */
    private static $update_existing;


    /**
     * Determine whether to overwrite existing files under assets by files downloaded from GatherContent.
     * If we're not overwriting, index _1, _2 etc. will be added until found non-existing filename
     *
     * @var bool
     * @config
     */
    private static $overwrite_files;


    /**
     * Determine whether to directly publish items that have status allowing them to be published in the CMS
     *
     * @config
     */
    private static $allow_publish;


    /**
     * Holder for various attributes and values processors, e.g. for removing prefix
     * from field name or other strings and values magic
     *
     * @config
     */
    private static $processors = [];


    /**
     * Holder for mappings between CMS page types, data objects and their attributes and fields AND GatherContent items
     *
     * @config
     */
    private static $mappings = [];


    /**
     * Mapping for GatherContent workflow statuses dividing item statuses into three groups:
     * - skip (items disregarded in the CMS)
     * - draft (items created as draft items, not published)
     * - publish (items published in the CMS, if settings allow)
     *
     * @config
     */
    private static $statuses = [];


    /**
     * Shortcut to current config object, loaded in constructor
     *
     * @var
     */
    private $cfg;


    /**
     * Module's main class constructor
     *
     * Checking for essential settings
     *
     */
    public function __construct() {

        $this->cfg = $this->config();

        // initial checks whether we have all settings needed [using trim for strings to work around missing __isset on ->config()->property]
        if (!(trim($this->cfg->api['url']) && trim($this->cfg->api['username']) && trim($this->cfg->api['key']))) {
            throw new Exception('GatherContent API details are not properly configured, url, username or key is missing or empty');
        }
        if (!(trim($this->cfg->plugin_api['accountname']) && trim($this->cfg->plugin_api['key']))) {
            throw new Exception('GatherContent Plugin API details are not properly configured, accountname or key is missing or empty');
        }
        if (!trim($this->cfg->project)) {
            throw new Exception('Name of the project within GatherContent is not configured');
        }


        $apiCfg = $this->cfg->api; // need to go via a variable to be able to assign back to the config object
        // check for trailing slashes in api url and add it if missing
        if (substr($apiCfg['url'], -1, 1) !== '/') {
            $apiCfg['url'] .= '/';
        }
        Config::inst()->update('SSGatherContent', 'api', $apiCfg);


        $pluginApiCfg = $this->cfg->plugin_api; // need to go via a variable to be able to assign back to the config object
        // check for trailing slashes in plugin api url and add it if missing
        if (substr($pluginApiCfg['url'], -1, 1) !== '/') {
            $pluginApiCfg['url'] .= '/';
        }
        // replace account name placeholder in the url as well
        if (strpos($pluginApiCfg['url'],'%%ACCOUNTNAME%%') !== false) {
            $pluginApiCfg['url'] = str_replace('%%ACCOUNTNAME%%', $pluginApiCfg['accountname'], $pluginApiCfg['url']);
        }
        Config::inst()->update('SSGatherContent', 'plugin_api', $pluginApiCfg);

    }


    /**
     * Request data from GatherContent using new API and return array containing response data and http code
     * as returned by curl
     *
     * If some parameters are provided via $params, the call uses POST instead of GET and the data is passed through.
     *
     *
     * @param string $method        part of the url to be added to the API url to make specific calls
     * @param array $params         POST data to be passed through to the endpoint
     * @return array                array containing response and http code returned by curl
     */
    private function callAPI($method = '', $params = []) {

        $url = $this->cfg->api['url'] . $method;
        $httpHeader = ['Accept: application/vnd.gathercontent.v0.5+json'];
        $userPwd = $this->cfg->api['username'] . ':' . $this->cfg->api['key'];

        return SSGatherContentTools::fetchAPI($url, $httpHeader, $userPwd, $params);

    }


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
    private function callPluginAPI($method = '', $params = []) {

        $url = $this->cfg->plugin_api['url'] . $method;
        $httpHeader = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
        $userPwd = $this->cfg->plugin_api['key'] . ":" . $this->cfg->plugin_api['password'];

        return SSGatherContentTools::fetchAPI($url, $httpHeader, $userPwd, $params);

    }


}