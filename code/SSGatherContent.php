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
     * Determine how to treat existing items (based on GatherContent unique item id) - overwrite or skip existing
     * CMS items by items from GatherContent, or create new ones
     *
     * Value can be 'skip', 'update', 'new' or 'replace', performing action as the constant suggests. Replace deletes the item and recreates it.
     *
     * If any other value is used, fallback is 'new'.
     *
     * @var bool
     * @config
     */
    private static $process_existing;


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
     * Determine whether to download files uploaded to GatherContent and linked to content items.
     *
     * @var bool
     * @config
     */
    private static $download_files;


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


        // check configuration of process_existing setting
        if (!is_string($this->cfg->process_existing) || !in_array(strtolower($this->cfg->process_existing), array('new', 'skip', 'update', 'replace'))) {
            throw new Exception('Existing items processing mode (config key "process_existing") has to be set to one of these options: new, skip, update or replace.');
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


    /**
     * Get a list of all Items for particular Project
     *
     * This method merges previously saved files list, if config allows, with items list loaded from GatherContent
     * for specified project based on its ID.
     *
     *
     * @param int $project_id       project ID to load items for
     * @return array                data
     */
    private function getItems($project_id) {

        // if we use saved files
        if ($this->cfg->use_saved_json_files) {
            $itemsFromJSONFiles = SSGatherContentTools::loadDataFromJSONByPattern($this->cfg->assets_subfolder_json, "project_{$project_id}_item_*.json");

            // transform to be indexed by item ID
            $itemsFromJSONFiles = SSGatherContentTools::transformArray($itemsFromJSONFiles, 'id', null, false, function($key) { return 'id_' . $key; });

            // add flag it was loaded from a file
            array_walk($itemsFromJSONFiles, function(&$item, $key) { $item['SSGC_source'] = 'file'; });

        } else {
            $itemsFromJSONFiles = array();
        }

        // if we have some API data
        $itemsFromAPI = $this->gcAPI->getItems($project_id);
        if ($itemsFromAPI) {

            // transform to be indexed by item ID
            $itemsFromAPI = SSGatherContentTools::transformArray($itemsFromAPI, 'id', null, false, function($key) { return 'id_' . $key; });

            // add flag it was loaded from the API
            array_walk($itemsFromAPI, function(&$item, $key) { $item['SSGC_source'] = 'api'; });

        } else {
            $itemsFromAPI = array();
        }

        // get API items overwrite JSON data items
        $items = array_merge($itemsFromJSONFiles, $itemsFromAPI);

        return $items;
    }


    /**
     * Import all content and all files from GatherContent into the CMS based on the YAML configuration.
     *
     */
    public function loadContentFromGatherContent() {

        // get mappings and create couple support variables
        $mappings = $this->cfg->mappings;

        $statuses_IdToName = array(); // map template names to IDs
        $templates_IdToName = array(); // map template names to IDs
        $template_to_class = array(); // map template IDs to classes (page types and data objects)

        $templates_order = array();  // order of templates how they should be processed

        $skipped_templates = array(); // do we skip some GC templates?

        $generic_processors_field = $this->cfg->processors['field'];
        $generic_processors_value = $this->cfg->processors['value'];

        // default mapping?
        $mappings_default = false;
        if (array_key_exists('default', $mappings)) {
            $mappings_default = $mappings['default'];

            foreach($mappings_default as $mapping_class => $mapping_details) {

                // extend the mapping details with some useful information
                $is_page = is_subclass_of($mapping_class, 'SiteTree');
                $is_dataObject = !$is_page && is_subclass_of($mapping_class, 'DataObject');
                $mapping_details['is_page'] = $is_page;
                $mapping_details['is_do'] = $is_dataObject;

                $mappings_default[$mapping_class] = $mapping_details;
            }
        }

        // standard mappings
        foreach($mappings['classes'] as $mapping_class => $mapping_details) {

            // extend the mapping details with some useful information
            $is_page = is_subclass_of($mapping_class, 'SiteTree');
            $is_dataObject = !$is_page && is_subclass_of($mapping_class, 'DataObject');
            $mapping_details['is_page'] = $is_page;
            $mapping_details['is_do'] = $is_dataObject;

            if (!array_key_exists($mapping_details['template'], $template_to_class)) {
                $template_to_class[$mapping_details['template']] = array();
            }
            $template_to_class[$mapping_details['template']][$mapping_class] = $mapping_details;
        }

        // templates order - undefined templates go last as they come from GatherContent
        if (array_key_exists('template_order', $mappings) && is_array($mappings['template_order'])) {
              $templates_order = $mappings['template_order'];
        }

        // skipped templates - templates we don't load from GatherContent at all
        if (array_key_exists('skipped_templates', $mappings) && is_array($mappings['skipped_templates'])) {
            $skipped_templates = $mappings['skipped_templates'];
        }

        // accounts
        $accounts = $this->gcAPI->getAccounts();

        // iterate over accounts and pick only the one we have configured
        foreach ($accounts as $single_account) {
            if (strtolower($single_account['slug']) !== strtolower($this->cfg->plugin_api['accountname'])) continue;

            $account_id = $single_account['id'];

            // projects
            $projects = $this->gcAPI->getProjects($account_id);
            if ($projects) {

                // iterate over projects and pick only the one we have configured
                foreach ($projects as $single_project) {
                    if (strtolower($single_project['name']) !== strtolower($this->cfg->project)) continue;

                    $project_id = $single_project['id'];

                    // get statuses and transform the array to be name indexed
                    $statuses = $this->gcAPI->getStatuses($project_id);
                    if ($statuses) {
                        $statuses_IdToName = SSGatherContentTools::transformArray($statuses, 'id', 'name');
                    }

                    // get templates and transform the array to be name indexed
                    $templates = $this->gcAPI->getTemplates($project_id);
                    if ($templates) {
                        $templates_IdToName = SSGatherContentTools::transformArray($templates, 'id', 'name');
                    }

                    $templates_order_orig = $templates_order;

                    // items
                    $items = $this->getItems($project_id);
                    if ($items) {

                        while (is_array($templates_order)) {

                            // get first template to process or NULL
                            $template_limit = array_shift($templates_order);

                            // if no ordered templates, last run, set to false
                            if ($template_limit === null) {
                                $templates_order = false;
                            }

                            // iterate over items
                            foreach ($items as $single_item) {
                                $item_id = $single_item['id'];
                                $item_parent_id = $single_item['parent_id'];
                                $item_template_id = $single_item['template_id'];

                                // helper not to iterate over already processed items
                                if (!array_key_exists('SSGC_processed', $single_item)) {
                                    $single_item['SSGC_processed'] = false;
                                }

                                // not the current template?
                                if ($template_limit && ($templates_IdToName[$item_template_id] !== $template_limit)) {
                                    continue;
                                // already processed template?
                                } elseif (($template_limit === null) && count($templates_order_orig) && (in_array($templates_IdToName[$item_template_id], $templates_order_orig))) {
                                    continue;
                                // skipped template?
                                } elseif (count($skipped_templates) && in_array($templates_IdToName[$item_template_id], $skipped_templates)) {
                                    continue;
                                // already processed?
                                } elseif ($single_item['SSGC_processed']) {
                                    continue;
                                }

                                // have we got the item from a previously downloaded file
                                if ($single_item['SSGC_source'] === 'file') {
                                    $item = $single_item;
                                // or should the item be loaded from the API
                                } else {        // previously if ($single_item['SSGC_source'] === 'api'), but we want to use API by default
                                    $item = $this->gcAPI->getItem($item_id);
                                }

                                // get item status and check whether we don't skip items with that status
                                $item_status_name = $item['status']['data']['name'];
                                if (in_array($item_status_name, $this->cfg->statuses['skip'])) continue;

                                // get template name
                                $item_template_name = $templates_IdToName[$item['template_id']];

                                // item specification from config
                                $item_spec = null;
                                if (array_key_exists($item_template_name, $template_to_class)) {
                                    $item_spec = $template_to_class[$item_template_name];
                                } else {
                                    if ($mappings_default) {
                                        $item_spec = $mappings_default;
                                    }
                                }

                                if ($item_spec) {
                                    $item_spec_details = reset($item_spec);
                                    $item_class = key($item_spec);
                                    $item_update = false;

                                    // get existing item, if it exists
                                    $item_instance = SSGatherContentTools::getItemByGCUniqueIdentifier($this->cfg->unique_identifier, $item_id, $item_class);

                                    // if the item exists and we don't have to create a new one
                                    if ((in_array($this->cfg->process_existing, array('skip', 'update'))) && ($item_instance instanceof $item_class) && ($item_instance->exists())) {

                                        // skip existing?
                                        if ($this->cfg->process_existing === 'skip') {
                                            continue;
                                        }

                                        // updating?
                                        if ($this->cfg->process_existing === 'update') {
                                            $item_update = true;
                                        }

                                    // item exists but we may need a new one?
                                    } elseif ((in_array($this->cfg->process_existing, array('replace', 'new'))) && ($item_instance instanceof $item_class) && ($item_instance->exists())) {

                                        // replace?
                                        if ($this->cfg->process_existing === 'replace') {
                                            $item_instance->delete();
                                        }

                                        $item_instance = new $item_class();

                                    // item doesn't exist
                                    } else {
                                        $item_instance = new $item_class();
                                    }

                                    // if configured, get class field & value processors
                                    $item_spec_details_processors = null;
                                    $item_spec_details_processors_field = array();
                                    $item_spec_details_processors_value = array();
                                    if (array_key_exists('processors', $item_spec_details)) {
                                        $item_spec_details_processors = $item_spec_details['processors'];

                                        // get class related filters from the specification for field name and value
                                        if (is_array($item_spec_details_processors) && array_key_exists('field', $item_spec_details_processors) && is_array($item_spec_details_processors['field'])) {
                                            $item_spec_details_processors_field = $item_spec_details_processors['field'];
                                        }
                                        if (is_array($item_spec_details_processors) && array_key_exists('value', $item_spec_details_processors) && is_array($item_spec_details_processors['value'])) {
                                            $item_spec_details_processors_value = $item_spec_details_processors['value'];
                                        }
                                    }

                                    // if configured, get class fields
                                    $item_spec_details_fields = array();
                                    if (array_key_exists('fields', $item_spec_details)) {
                                        $item_spec_details_fields = $item_spec_details['fields'];
                                    }

                                    // if configured, get class parent item definition
                                    $item_spec_details_parent = array();
                                    $item_spec_details_parent_class = null;
                                    $item_spec_details_parent_title = null;
                                    if (array_key_exists('parent', $item_spec_details)) {
                                        $item_spec_details_parent = $item_spec_details['parent'];

                                        // get class related parent item class and title
                                        if (is_array($item_spec_details_parent) && array_key_exists('class', $item_spec_details_parent)) {
                                            $item_spec_details_parent_class = $item_spec_details_parent['class'];
                                        }
                                        if (is_array($item_spec_details_parent) && array_key_exists('title', $item_spec_details_parent)) {
                                            $item_spec_details_parent_title = $item_spec_details_parent['title'];
                                        }

                                    }

                                    $item_spec_details_fields_mappings = array();
                                    $item_spec_details_fields_mappings_cms_to_gc = array();
                                    // if configured, get class fields mappings
                                    if (array_key_exists('mappings', $item_spec_details_fields)) {
                                        $item_spec_details_fields_mappings = $item_spec_details_fields['mappings'];
                                    }

                                    // if configured, get class skipped fields
                                    $item_spec_details_fields_skip = array();
                                    if (array_key_exists('skip', $item_spec_details_fields)) {
                                        $item_spec_details_fields_skip = $item_spec_details_fields['skip'];
                                    }

                                    // get class field configuration from the CMS
                                    $item_class_db = $item_instance->db();
                                    $item_class_has_one = $item_instance->has_one();
                                    $item_class_has_many = $item_instance->has_many();
                                    $item_class_many_many = $item_instance->many_many();
                                    $item_class_belong_to = $item_instance->belongs_to();

                                    $item_content = array();
                                    if (array_key_exists('config', $item)) {
                                        $item_content = $item['config'];
                                    }

                                    if (is_array($item_content)) {
                                        foreach ($item_content as $item_section) {

                                            // elements
                                            $item_section_elements = array();
                                            if (array_key_exists('elements', $item_section)) {
                                                $item_section_elements = $item_section['elements'];
                                            }

                                            // iterate over section elements if possible
                                            if (is_array($item_section_elements)) {
                                                foreach ($item_section_elements as $item_section_element) {

                                                    if (is_array($item_section_element)) {

                                                        $item_section_element_type = $item_section_element['type'];  // field type

                                                        // skip guidelines, they don't carry any content
                                                        if ($item_section_element_type === 'section') {
                                                            continue;
                                                        }
                                                        $item_section_element_name = $item_section_element['label']; // field name
                                                        $item_section_element_value = null; // field value

                                                        // check if the field is in the skip config
                                                        if (is_array($item_spec_details_fields_skip) && in_array($item_section_element_name, $item_spec_details_fields_skip)) {
                                                            continue;
                                                        }

                                                        // field cms mapping and processors variables init
                                                        $item_spec_details_fields_mappings_field = null;
                                                        $item_spec_details_field_mappings_lookup = null;
                                                        $item_spec_details_field_mappings_lookup_field = 'Title';
                                                        $item_spec_details_field_mappings_lookup_create = false;
                                                        $item_spec_details_field_processors_field = array();
                                                        $item_spec_details_field_processors_value = array();
                                                        $item_spec_details_field_translations = array();

                                                        // set variables based on item specification
                                                        if (is_array($item_spec_details_fields_mappings) && array_key_exists($item_section_element_name, $item_spec_details_fields_mappings)) {
                                                            // get string mapping or array of parameters for the field
                                                            $item_spec_details_fields_mappings_field = $item_spec_details_fields_mappings[$item_section_element_name];

                                                            // if we've got an array, we may have processors and cms mapping
                                                            if (is_array($item_spec_details_fields_mappings_field)) {

                                                                // get processors, if defined
                                                                if (array_key_exists('processors', $item_spec_details_fields_mappings_field)) {
                                                                    $item_spec_details_field_processors = $item_spec_details_fields_mappings_field['processors'];

                                                                    // get class related filters from the specification for field name and value
                                                                    if (is_array($item_spec_details_field_processors) && array_key_exists('field', $item_spec_details_field_processors) && is_array($item_spec_details_field_processors['field'])) {
                                                                        $item_spec_details_field_processors_field = $item_spec_details_field_processors['field'];
                                                                    }
                                                                    if (is_array($item_spec_details_field_processors) && array_key_exists('value', $item_spec_details_field_processors) && is_array($item_spec_details_field_processors['value'])) {
                                                                        $item_spec_details_field_processors_value = $item_spec_details_field_processors['value'];
                                                                    }
                                                                }

                                                                // get lookup field, if defined, defaulting to 'Title', for items, that need to lookup
                                                                //their value based on the provided value, like has_one, has_many or many_many
                                                                if (array_key_exists('lookup', $item_spec_details_fields_mappings_field)) {
                                                                    $item_spec_details_field_mappings_lookup = $item_spec_details_fields_mappings_field['lookup'];
                                                                }

                                                                // get translations for the field, if defined as an array
                                                                if (array_key_exists('translations', $item_spec_details_fields_mappings_field) && is_array($item_spec_details_fields_mappings_field['translations'])) {
                                                                    $item_spec_details_field_translations = $item_spec_details_fields_mappings_field['translations'];
                                                                }

                                                                // get cms mapping, if defined, overwriting the variable, therefore it has to be last in this block
                                                                if (array_key_exists('cms', $item_spec_details_fields_mappings_field)) {
                                                                    $item_spec_details_fields_mappings_field = $item_spec_details_fields_mappings_field['cms'];
                                                                }

                                                            }
                                                        }

                                                        // determine element type and collect value based on the type
                                                        switch ($item_section_element_type) {
                                                            case 'text':
                                                                // get the value
                                                                $item_section_element_value = $item_section_element['value'];

                                                                break;

                                                            case 'choice_radio':
                                                                // iterate over options and pick single (first) item with selected == true
                                                                $item_section_element_options = $item_section_element['options'];
                                                                foreach ($item_section_element_options as $item_section_element_option) {
                                                                    if ($item_section_element_option['selected']) {
                                                                        $item_section_element_value = $item_section_element_option['label'];
                                                                        break;
                                                                    }
                                                                }

                                                                break;

                                                            case 'choice_checkbox':
                                                                // iterate over options, pick items with selected == true and add them to an array
                                                                $item_section_element_value = array();
                                                                $item_section_element_options = $item_section_element['options'];

                                                                foreach ($item_section_element_options as $item_section_element_option) {
                                                                    if ($item_section_element_option['selected']) {
                                                                        $item_section_element_value[] = $item_section_element_option['label'];
                                                                        break;
                                                                    }
                                                                }

                                                                break;

                                                            case 'files':
                                                                // download file info from the API based on the field's GC name (identifier)
                                                                $item_section_element_value = $this->gcAPI->getFileByItemAndField($item_id, $item_section_element['name']);

                                                                break;
                                                        }

                                                        // if we have configured exact mapping, use that
                                                        if ($item_spec_details_fields_mappings_field !== null) {
                                                            $item_section_element_field = $item_spec_details_fields_mappings_field;

                                                            // apply filters to the field name otherwise
                                                        } else {

                                                            // apply generic filters to the field name
                                                            $item_section_element_name = SSGatherContentTools::applyTransformationFilters($generic_processors_field, $item_section_element_name);
                                                            // apply class specific filters to the field name
                                                            $item_section_element_name = SSGatherContentTools::applyTransformationFilters($item_spec_details_processors_field, $item_section_element_name);
                                                            // apply field specific filters to the field name
                                                            $item_section_element_name = SSGatherContentTools::applyTransformationFilters($item_spec_details_field_processors_field, $item_section_element_name);

                                                            // cms destination field
                                                            $item_section_element_field = $item_section_element_name;
                                                        }

                                                        // apply filters to the value if it's not a file
                                                        if ($item_section_element_type !== 'files') {

                                                            // apply generic filters to the field value
                                                            $item_section_element_value = SSGatherContentTools::applyTransformationFilters($generic_processors_value, $item_section_element_value);
                                                            // apply class specific filters to the field value
                                                            $item_section_element_value = SSGatherContentTools::applyTransformationFilters($item_spec_details_processors_value, $item_section_element_value);
                                                            // apply field specific filters to the field value
                                                            $item_section_element_value = SSGatherContentTools::applyTransformationFilters($item_spec_details_field_processors_value, $item_section_element_value);
                                                        }

                                                        // apply translation, if defined. pick the first matching, but iterate over array
                                                        if (!empty($item_spec_details_field_translations)) {
                                                            foreach ($item_spec_details_field_translations as $translate_from => $translate_to) {
                                                                if (!is_array($item_section_element_value)) {
                                                                    if ($item_section_element_value === $translate_from) {
                                                                        $item_section_element_value = $translate_to;
                                                                        break;
                                                                    }
                                                                } else {
                                                                    foreach ($item_section_element_value as $key => $item_section_element_value_item) {
                                                                        if ($item_section_element_value_item === $translate_from) {
                                                                            $item_section_element_value[$key] = $translate_to;
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }

                                                        $has_matching_property = false;
                                                        $has_set_value = false;

                                                        if (array_key_exists($item_section_element_field, $item_class_db)) {

                                                            if (strpos(strtolower($item_section_element_field), 'enum') === 0) {
                                                                // TODO add check for the value

                                                                if ($item_section_element_value) {
                                                                    $item_instance->$item_section_element_field = $item_section_element_value;
                                                                    $has_set_value = true;
                                                                }

                                                            } else {

                                                                if (($item_class_db[$item_section_element_field] === 'MultiValueField') || (is_subclass_of($item_class_db[$item_section_element_field], 'MultiValueField'))) {
                                                                    if ($item_section_element_value && !is_array($item_section_element_value)) {
                                                                        $item_section_element_value = array($item_section_element_value);
                                                                    }
                                                                }

                                                                if ($item_section_element_value) {
                                                                    $item_instance->$item_section_element_field = $item_section_element_value;
                                                                    $has_set_value = true;
                                                                }

                                                            }

                                                            $has_matching_property = true;
                                                        }


                                                        // define lookup options
                                                        if ($item_spec_details_field_mappings_lookup) {
                                                            if (is_array($item_spec_details_field_mappings_lookup)) {
                                                                if (array_key_exists('field', $item_spec_details_field_mappings_lookup)) {
                                                                    $item_spec_details_field_mappings_lookup_field = $item_spec_details_field_mappings_lookup['field'];
                                                                }
                                                                if (array_key_exists('create', $item_spec_details_field_mappings_lookup)) {
                                                                    $item_spec_details_field_mappings_lookup_create = $item_spec_details_field_mappings_lookup['create'];
                                                                }
                                                            } else {
                                                                $item_spec_details_field_mappings_lookup_field = $item_spec_details_field_mappings_lookup;
                                                            }
                                                        }

                                                        if (array_key_exists($item_section_element_field, $item_class_has_one)) {
                                                            // TODO check whether the item exists before linking, if not file

                                                            if ($item_section_element_type !== 'files') {
                                                                $has_one_item = SSGatherContentTools::getItemByLookupField($item_spec_details_field_mappings_lookup_field, $item_section_element_value, $item_class_has_one[$item_section_element_field], $item_spec_details_field_mappings_lookup_create);

                                                                if ($has_one_item) {
                                                                    $item_instance->{$item_section_element_field . 'ID'} = $has_one_item->ID;
                                                                    $has_set_value = true;
                                                                }

                                                            } else {
                                                                // if file from GC, check if we have the file, if not -> download, and link by ID
                                                                $has_one_file = SSGatherContentTools::getItemByGCUniqueIdentifier($this->cfg->unique_identifier, $item_section_element_value['id'], 'File');
                                                                if ($has_one_file) {
                                                                    $item_instance->$item_section_element_field = $has_one_file->ID;
                                                                    $has_set_value = true;
                                                                } else {
                                                                    if ($this->cfg->download_files) {
                                                                        $has_one_fileID = $this->downloadFileIntoAssetsSubfolder($item_section_element_value);
                                                                        if ($has_one_fileID) {
                                                                            $item_instance->{$item_section_element_field . 'ID'} = $has_one_fileID;
                                                                            $has_set_value = true;
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            $has_matching_property = true;
                                                        }

                                                        if (array_key_exists($item_section_element_field, $item_class_has_many)) {
                                                            // TODO check whether the item exists before linking, if not file

                                                            if ($item_section_element_type !== 'files') {
                                                                if (!is_array($item_section_element_value)) {
                                                                    if (trim($item_section_element_value)) {
                                                                        $item_section_element_value = array($item_section_element_value);
                                                                    } else {
                                                                        $item_section_element_value = array();
                                                                    }
                                                                }
                                                                foreach ($item_section_element_value as $item_section_element_value_item) {
                                                                    $has_many_item = SSGatherContentTools::getItemByLookupField($item_spec_details_field_mappings_lookup_field, $item_section_element_value_item, $item_class_has_many[$item_section_element_field], $item_spec_details_field_mappings_lookup_create);
                                                                    if ($has_many_item) {

                                                                        // updating?
                                                                        if (!$item_update) {
                                                                            // no -> safe to add to the list
                                                                            $item_instance->$item_section_element_field()->add($has_many_item);
                                                                            $has_set_value = true;
                                                                        } else {
                                                                            // add only if the item is in not already in the list
                                                                            if (!$item_instance->$item_section_element_field(array($item_class_has_many[$item_section_element_field].'.ID' => $has_many_item->ID))->exists()) {
                                                                                $item_instance->$item_section_element_field()->add($has_many_item);
                                                                                $has_set_value = true;
                                                                            }
                                                                        }
                                                                    }
                                                                }

                                                            } else {
                                                                // if file from GC, check if we have the file, if not -> download, and link by ID
                                                                $has_many_file = SSGatherContentTools::getItemByGCUniqueIdentifier($this->cfg->unique_identifier, $item_section_element_value['id'], 'File');
                                                                if ($has_many_file) {
                                                                    $item_instance->$item_section_element_field->add($has_many_file);
                                                                    $has_set_value = true;
                                                                } else {
                                                                    if ($this->cfg->download_files) {
                                                                        $has_many_fileID = $this->downloadFileIntoAssetsSubfolder($item_section_element_value);

                                                                        if ($has_many_fileID) {

                                                                            // updating?
                                                                            if (!$item_update) {
                                                                                // no -> safe to add to the list
                                                                                $item_instance->$item_section_element_field()->add(File::get_by_id($item_class_has_many[$item_section_element_field], $has_many_fileID));
                                                                                $has_set_value = true;
                                                                            } else {
                                                                                // add only if the item is in not already in the list
                                                                                if (!$item_instance->$item_section_element_field(array($item_class_has_many[$item_section_element_field].'.ID' => $has_many_fileID))->exists()) {
                                                                                    $item_instance->$item_section_element_field()->add(File::get_by_id($item_class_has_many[$item_section_element_field], $has_many_fileID));
                                                                                    $has_set_value = true;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            $has_matching_property = true;
                                                        }

                                                        if (array_key_exists($item_section_element_field, $item_class_many_many)) {
                                                            // TODO check whether the item exists before linking, if not file

                                                            if ($item_section_element_type !== 'files') {
                                                                if (!is_array($item_section_element_value)) {
                                                                    if (trim($item_section_element_value)) {
                                                                        $item_section_element_value = array($item_section_element_value);
                                                                    } else {
                                                                        $item_section_element_value = array();
                                                                    }
                                                                }

                                                                foreach ($item_section_element_value as $item_section_element_value_item) {
                                                                    $many_many_item = SSGatherContentTools::getItemByLookupField($item_spec_details_field_mappings_lookup_field, $item_section_element_value_item, $item_class_many_many[$item_section_element_field], $item_spec_details_field_mappings_lookup_create);
                                                                    if ($many_many_item) {

                                                                        // updating?
                                                                        if (!$item_update) {
                                                                            // no -> safe to add to the list
                                                                            $item_instance->$item_section_element_field()->add($many_many_item);
                                                                            $has_set_value = true;
                                                                        } else {
                                                                            // add only if the item is in not already in the list
                                                                            if (!$item_instance->$item_section_element_field(array($item_class_many_many[$item_section_element_field].'.ID' => $many_many_item->ID))->exists()) {
                                                                                $item_instance->$item_section_element_field()->add($many_many_item);
                                                                                $has_set_value = true;
                                                                            }
                                                                        }
                                                                    }
                                                                }

                                                            } else {
                                                                // if file from GC, check if we have the file, if not -> download, and link by ID
                                                                $many_many_file = SSGatherContentTools::getItemByGCUniqueIdentifier($this->cfg->unique_identifier, $item_section_element_value['id'], 'File');
                                                                if ($many_many_file) {
                                                                    $item_instance->$item_section_element_field->add($many_many_file);
                                                                } else {
                                                                    if ($this->cfg->download_files) {
                                                                        $many_many_fileID = $this->downloadFileIntoAssetsSubfolder($item_section_element_value);
                                                                        if ($many_many_fileID) {

                                                                            // updating?
                                                                            if (!$item_update) {
                                                                                // no -> safe to add to the list
                                                                                $item_instance->$item_section_element_field()->add(File::get_by_id($item_class_many_many[$item_section_element_field], $many_many_fileID));
                                                                                $has_set_value = true;
                                                                            } else {
                                                                                // add only if the item is in not already in the list
                                                                                if (!$item_instance->$item_section_element_field(array($item_class_many_many[$item_section_element_field].'.ID' => $many_many_fileID))->exists()) {
                                                                                    $item_instance->$item_section_element_field()->add(File::get_by_id($item_class_many_many[$item_section_element_field], $many_many_fileID));
                                                                                    $has_set_value = true;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            $has_matching_property = true;
                                                        }

                                                    } // if is_array($item_section_element)

                                                } // foreach ($item_section_elements)

                                            } // if is_array($item_section_elements)

                                        } // foreach ($item_content)

                                    }; // if is_array($item_content)

                                    if ($item_instance->hasExtension('SSGatherContentDataExtension')) {
                                        $item_instance->GC_storeAllInfo($item_id, $item_parent_id);
                                    }

                                    if ($item_instance instanceof SiteTree) {

                                        // if class and title provided, try to find or create parent item
                                        if (($item_spec_details_parent_class !== null) && is_subclass_of($item_spec_details_parent_class, 'SiteTree') && $item_spec_details_parent_title) {
                                            $parent = SSGatherContentTools::getItemByLookupField('Title', $item_spec_details_parent_title, $item_spec_details_parent_class, true, $this->cfg->allow_publish);
                                            if ($parent && $parent->ID) {
                                                $item_instance->ParentID = $parent->ID;
                                            }
                                        }

                                        $item_instance->write();
                                        if ($this->cfg->allow_publish) {
                                            $item_instance->publish('Stage', 'Live');
                                            $item_instance->doRestoreToStage();
                                        }
                                    } else {
                                        $item_instance->write();
                                    }

                                    $single_item['SSGC_processed'] = true;

                                } // if ($item_spec)

                            } // foreach ($items)

                        } // while (is_array($templates_order))

                    } // if ($items)

                } // foreach ($projects)

            } // if ($projects)

        } // foreach ($accounts)

    }




}
