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
    private static $api = array();


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
    private static $plugin_api = array();


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
     * and are descendants of SiteTree. DataObject are basically either published or don't exist.
     *
     * @var bool
     * @config
     */
    private static $allow_publish;


    /**
     * Unique identifier in the CMS that the module can use to determine whether the item already exists in the CMS
     * and we need to check if we can overwrite it or not
     *
     * @var bool
     * @config
     */
    private static $unique_identifier;


    /**
     * Holder for various attributes and values processors, e.g. for removing prefix
     * from field name or other strings and values magic
     *
     * @var array
     * @config
     */
    private static $processors = array();


    /**
     * Holder for mappings between CMS page types, data objects and their attributes and fields AND GatherContent items
     *
     * @var array
     * @config
     */
    private static $mappings = array();


    /**
     * Mapping for GatherContent workflow statuses dividing item statuses into three groups:
     * - skip (items disregarded in the CMS)
     * - draft (items created as draft items, not published) - this is default if a status does not fall into another group
     * - publish (items published in the CMS, if settings allow)
     *
     * @var array
     * @config
     */
    private static $statuses = array();


    /**
     * Translate value to another value. Array holds pairs GC-value => CMS-value and translations are applied to all
     * values coming from GatherContent. Per field translation configuration gets applied afterwards, if configured.
     *
     * Useful for example for GatherContent's choice_radio implemented as enum in the CMS where the values in
     * GatherContent are much longer and need to be abbreviated for the enum
     *
     * @var array
     * @config
     */
    private static $translations = array();


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


        // prepare standard API configuration
        $apiCfg = $this->cfg->api; // need to go via a variable to be able to assign back to the config object
        // check for trailing slashes in the API url and add it if missing
        if (substr($apiCfg['url'], -1, 1) !== '/') {
            $apiCfg['url'] .= '/';
        }
        Config::inst()->remove('SSGatherContent', 'api'); // remove needed otherwise arrays get merged, not replaced
        Config::inst()->update('SSGatherContent', 'api', $apiCfg);


        // prepare plugin API configuration
        $pluginApiCfg = $this->cfg->plugin_api; // need to go via a variable to be able to assign back to the config object
        // check for trailing slashes in the plugin API url and add it if missing
        if (substr($pluginApiCfg['url'], -1, 1) !== '/') {
            $pluginApiCfg['url'] .= '/';
        }
        // replace account name placeholder in the url as well
        if (strpos($pluginApiCfg['url'],'%%ACCOUNTNAME%%') !== false) {
            $pluginApiCfg['url'] = str_replace('%%ACCOUNTNAME%%', $pluginApiCfg['accountname'], $pluginApiCfg['url']);
        }
        Config::inst()->remove('SSGatherContent', 'plugin_api'); // remove needed otherwise arrays get merged, not replaced
        Config::inst()->update('SSGatherContent', 'plugin_api', $pluginApiCfg);


        // ensure all folders are configured before they're used
        if (!trim($this->cfg->assets_subfolder)) {
            throw new Exception('Assets subfolder for downloaded files has to be configured.');
        }
        if (!trim($this->cfg->assets_subfolder_backup)) {
            throw new Exception('Assets subfolder for backups has to be configured.');
        }
        if ($this->cfg->save_json_files) {
            if (!trim($this->cfg->assets_subfolder_json)) {
                throw new Exception('Assets subfolder for JSON data has to be configured when "save_json_files" option is turned on.');
            }
        }


        // ensure status mappings are all arrays
        $statuses = $this->cfg->statuses;
        if ((array_key_exists('skip', $statuses) && $statuses['skip'] && !is_array($statuses['skip']))
         || (array_key_exists('draft', $statuses) && $statuses['draft'] && !is_array($statuses['draft']))
         || (array_key_exists('publish', $statuses) && $statuses['publish'] && !is_array($statuses['publish']))) {
            throw new Exception('All status mappings have to be configured as arrays.');
        }
        if (!array_key_exists('skip', $statuses)) {
            $statuses['skip'] = array();
        }
        if (!array_key_exists('draft', $statuses)) {
            $statuses['draft'] = array();
        }
        if (!array_key_exists('publish', $statuses)) {
            $statuses['publish'] = array();
        }
        Config::inst()->remove('SSGatherContent', 'statuses'); // remove needed otherwise arrays get merged, not replaced
        Config::inst()->update('SSGatherContent', 'statuses', $statuses);


        // ensure processors are all arrays (if defined)
        $processors = $this->cfg->processors;
        if ((array_key_exists('field', $processors) && $processors['field'] && !is_array($processors['field']))
         || (array_key_exists('value', $processors) && $processors['value'] && !is_array($processors['value']))) {
            throw new Exception('All field and value processors have to be configured as arrays.');
        }
        if (!array_key_exists('field', $processors)) {
            $processors['field'] = array();
        }
        if (!array_key_exists('value', $processors)) {
            $processors['value'] = array();
        }
        Config::inst()->remove('SSGatherContent', 'processors'); // remove needed otherwise arrays get merged, not replaced
        Config::inst()->update('SSGatherContent', 'processors', $processors);


        // ensure translations are array
        if (!is_array($this->cfg->translations)) {
            throw new Exception('Translations have to be configured as an array of "value -> translated value" pairs.');
        }


        // instantiate and assign SS GC API
        $this->gcAPI = new SSGatherContentAPI($this->cfg);

    }


    /**
     * Download file previously uploaded to GatherContent using its GC S3 identifier and original filename
     *
     * This function uses general module configuration around the assets subfolder path and overwriting existing files.
     * If the GatherContentDataExtensions is configured for files, store GC item's ID and created/lastUpdate date & time.
     *
     * @param string|array $GCItem          either GatherContent item ID or a full file dataset from which we read needed attributes
     * @param string|null $S3Identifier     GatherContent S3 identifier of the file
     * @param string|null $filename         original filename as uploaded to GatherContent
     * @return bool|File                    ID of the file created under assets OR false when unsuccessful
     */
    private function downloadFileIntoAssetsSubfolder($GCItem, $S3Identifier = null, $filename = null) {
        if (is_array($GCItem) && is_null($S3Identifier) && is_null($filename)) {
            if (array_key_exists('filename', $GCItem)) {
                $S3Identifier = $GCItem['filename'];
            }
            if (array_key_exists('original_filename', $GCItem)) {
                $filename = $GCItem['original_filename'];
            }
            if (array_key_exists('id', $GCItem)) {
                $GCItem = $GCItem['id'];
            }
        }

        if ($GCItem && $S3Identifier && $filename) {
            $fileID = SSGatherContentTools::downloadFileIntoAssetsSubfolder($this->cfg->s3_file_store_url, $S3Identifier, $this->cfg->assets_subfolder, $filename, (bool)$this->cfg->overwrite_files);
        } else {
            return false;
        }

        $file = File::get()->filter(array('ID' => $fileID))->first();

        if ($file && $file->has_extension('SSGatherContentDataExtension')) {
            $file->GC_storeAllInfo($GCItem);
        }

        return $fileID;
    }


    /**
     * Backup all content and all files from GatherContent into JSON or standard files (content or file)
     * under configured assets subfolder.
     * Those backup files can't be easily used without further care to restore data in GatherContent, they should
     * really serve as a snapshot.
     *
     * Configuration also determines whether the backup folder has date and time subfolder created each time the backup
     * is run.
     */
    public function backupContentFromGatherContent() {

        if ($this->cfg->suffix_backup_with_datetime) {
            $backup_folder_files = SSGatherContentTools::joinPaths($this->cfg->assets_subfolder_backup, date('Y-m-d_H-i-s'));
            Config::inst()->update('SSGatherContent', 'assets_subfolder_backup', $backup_folder_files);
        }

        // me
        $this->gcAPI->backupMe();

        // accounts
        $accounts = $this->gcAPI->backupAccounts();
        if ($accounts) {
            $accounts = $accounts['data'];

            // iterate over accounts and pick only the one we have configured
            foreach ($accounts as $single_account) {
                if (strtolower($single_account['slug']) !== strtolower($this->cfg->plugin_api['accountname'])) continue;

                $account_id = $single_account['id'];

                // projects
                $projects = $this->gcAPI->backupProjects($account_id);
                if ($projects) {
                    $projects = $projects['data'];

                    // iterate over projects and pick only the one we have configured
                    foreach ($projects as $single_project) {
                        if (strtolower($single_project['name']) !== strtolower($this->cfg->project)) continue;

                        $project_id = $single_project['id'];

                        // statuses
                        $statuses = $this->gcAPI->backupStatuses($project_id);
                        if ($statuses) {
                            $statuses = $statuses['data'];

                            // iterate over statuses
                            foreach ($statuses as $single_status) {
                                $status_id = $single_status['id'];

                                $this->gcAPI->backupStatus($project_id, $status_id);
                            }
                        }

                        // templates
                        $templates = $this->gcAPI->backupTemplates($project_id);
                        if ($templates) {
                            $templates = $templates['data'];

                            // iterate over templates
                            foreach ($templates as $single_template) {
                                $template_id = $single_template['id'];

                                $this->gcAPI->backupTemplate($template_id);
                            }
                        }

                        // files by project
                        $files = $this->gcAPI->backupFilesByProject($project_id);
                        if ($files) {
                            $files = $files['data'];

                            // iterate over files
                            foreach ($files as $single_file) {
                                $file_id = $single_file['id'];

                                $file = $this->gcAPI->backupFileByProject($file_id);
                                if ($file) {
                                    $file = $file['data'];

                                    // download file into assets
                                    SSGatherContentTools::backupFileIntoAssetsSubfolder($this->cfg->s3_file_store_url, $file['filename'], SSGatherContentTools::joinPaths($this->cfg->assets_subfolder_backup, 'files'), $file['original_filename']);
                                }
                            }
                        }

                        // project
                        $this->gcAPI->backupProject($project_id);

                        // items
                        $items = $this->gcAPI->backupItems($project_id);
                        if ($items) {
                            $items = $items['data'];

                            // iterate over items
                            foreach ($items as $single_item) {
                                $item_id = $single_item['id'];

                                $this->gcAPI->backupItem($item_id);
                            }
                        }
                    }
                }
            }
        }
    }




}
