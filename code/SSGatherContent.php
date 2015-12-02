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
     * @var array
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
     * @var array
     * @config
     */
    private static $plugin_api = [];


    /**
     * GatherContent project name
     *
     * @var string
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
     * @var string
     * @config
     */
    private static $assets_subfolder_json = '';


    /**
     * Assets folder used to store downloaded JSON data as a backup
     *
     * @var string
     * @config
     */
    private static $assets_subfolder_backup = '';


    /**
     * Determine whether to create a backup subfolder based on date and time of the backup
     *
     * @var bool
     * @config
     */
    private static $suffix_backup_with_datetime;


    /**
     * Determine whether JSON data is saved when downloaded from GatherContent
     * If the file exists for given API call, it's always replaced by the new one
     *
     * @var bool
     * @config
     */
    private static $save_json_files;


    /**
     * Determine whether to use JSON data files for Items no longer referenced
     * by GatherContent, but possibly still valid for the project
     *
     * @var bool
     * @config
     */
    private static $use_saved_json_files;


    /**
     * Determine whether to overwrite existing CMS items by items from GatherContent,
     * based on GatherContent unique item id
     *
     * @var bool
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
     * @var bool
     * @config
     */
    private static $allow_publish;


    /**
     * Holder for various attributes and values processors, e.g. for removing prefix
     * from field name or other strings and values magic
     *
     * @var array
     * @config
     */
    private static $processors = [];


    /**
     * Holder for mappings between CMS page types, data objects and their attributes and fields AND GatherContent items
     *
     * @var array
     * @config
     */
    private static $mappings = [];


    /**
     * Mapping for GatherContent workflow statuses dividing item statuses into three groups:
     * - skip (items disregarded in the CMS)
     * - draft (items created as draft items, not published)
     * - publish (items published in the CMS, if settings allow)
     *
     * @var array
     * @config
     */
    private static $statuses = [];


    /**
     * Shortcut to current config object, loaded in constructor
     *
     * @var Config_ForClass|null
     */
    private $cfg;


    /**
     * Holder for an instance of GatherContent API class
     *
     * @var SSGatherContentAPI
     */
    private $gcAPI;


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
        // check for trailing slashes in the API url and add it if missing
        if (substr($apiCfg['url'], -1, 1) !== '/') {
            $apiCfg['url'] .= '/';
        }
        Config::inst()->update('SSGatherContent', 'api', $apiCfg);


        $pluginApiCfg = $this->cfg->plugin_api; // need to go via a variable to be able to assign back to the config object
        // check for trailing slashes in the plugin API url and add it if missing
        if (substr($pluginApiCfg['url'], -1, 1) !== '/') {
            $pluginApiCfg['url'] .= '/';
        }
        // replace account name placeholder in the url as well
        if (strpos($pluginApiCfg['url'],'%%ACCOUNTNAME%%') !== false) {
            $pluginApiCfg['url'] = str_replace('%%ACCOUNTNAME%%', $pluginApiCfg['accountname'], $pluginApiCfg['url']);
        }
        Config::inst()->update('SSGatherContent', 'plugin_api', $pluginApiCfg);


        // instantiate and assign SS GC API
        $this->gcAPI = new SSGatherContentAPI($this->cfg);


    }




}