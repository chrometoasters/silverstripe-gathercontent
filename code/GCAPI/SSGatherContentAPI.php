<?php

/**
 * Class SSGatherContentAPI
 *
 * Implementation of functions communicating with GatherContent API via GC API wrappers
 *
 */
class SSGatherContentAPI {

    /**
     * Holder for module's configuration
     *
     * @var
     */
    private $gcConfig;


    /**
     * Holder for GatherContent API Wrapper instance
     *
     * @var SSGatherContentAPIWrapper
     */
    private $gcAPI;


    /**
     * Holder for GatherContent Plugin API Wrapper instance
     *
     * @var SSGatherContentPluginAPIWrapper
     */
    private $gcPluginAPI;


    /**
     * Constructor, receiving and storing config data, instantiating API wrappers
     *
     * @param Config_ForClass $gcConfig
     */
    public function __construct($gcConfig) {

        // store module's config
        $this->gcConfig = $gcConfig;

        // instantiate and assign standard GC API wrapper
        $this->gcAPI = new SSGatherContentAPIWrapper($this->gcConfig->api);

        // instantiate and assign plugin GC API wrapper
        $this->gcPluginAPI = new SSGatherContentPluginAPIWrapper($this->gcConfig->plugin_api);
    }


    /**
     * Retrieve information about the current logged in GC user
     * https://gathercontent.com/developers/me/
     */
    public function getMe() {
        $data = $this->gcAPI->readAPI('me');
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, 'me.json');
        }
        return $data;
    }


    /**
     * Retrieve a list of all Accounts associated with the current logged in GC user
     * https://gathercontent.com/developers/accounts/get-accounts/
     */
    public function getAccounts() {
        $data = $this->gcAPI->readAPI('accounts');
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, 'accounts.json');
        }
        return $data;
    }


    /**
     * Retrieve a list of all Projects associated with the current logged in GC user
     * https://gathercontent.com/developers/projects/get-projects/
     *
     * @param int $account_id       account ID to fetch projects for
     * @return array|bool           data OR false
     */
    public function getProjects($account_id) {
        $method = 'projects?account_id=' . intval($account_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "account_{$account_id}_projects.json");
        }
        return $data;
    }


    /**
     * Retrieve a list of all Projects associated with the current logged in GC user
     * https://gathercontent.com/developers/projects/get-projects-by-id/
     *
     * @param int $project_id       project ID
     * @return array|bool           data OR false
     */
    public function getProject($project_id) {
        $method = 'projects/' . intval($project_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}.json");
        }
        return $data;
    }


    /**
     * Get a list of all Items for particular Project.
     * https://gathercontent.com/developers/items/get-items/
     *
     * @param int $project_id       project ID to fetch items for
     * @return array|bool           data OR false
     */
    public function getItems($project_id) {
        $method = 'items?project_id=' . intval($project_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_items.json");
        }
        return $data;
    }


    /**
     * Get all data related to a particular Item.
     * https://gathercontent.com/developers/items/get-items-by-id/
     *
     * @param int $item_id          item ID
     * @return array|bool           data OR false
     */
    public function getItem($item_id) {
        $method = 'items/' . intval($item_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            $project_id = $data['project_id'];
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_item_{$item_id}.json");
        }
        return $data;
    }


    /**
     * Retrieve a list of all Templates associated with particular Project.
     * https://gathercontent.com/developers/templates/get-templates/
     *
     * @param int $project_id       project ID to fetch templates for
     * @return array|bool           data OR false
     */
    public function getTemplates($project_id) {
        $method = 'templates?project_id=' . intval($project_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_templates.json");
        }
        return $data;
    }


    /**
     * Get all data related to a particular Template.
     * https://gathercontent.com/developers/templates/get-templates-by-id/
     *
     * @param int $template_id      template ID
     * @return array|bool           data OR false
     */
    public function getTemplate($template_id) {
        $method = 'templates/' . intval($template_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            $project_id = $data['project_id'];
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_template_{$template_id}.json");
        }
        return $data;
    }


    /**
     * Retrieve a list of all Statuses associated with a particular Project.
     * https://gathercontent.com/developers/projects/get-projects-statuses/
     *
     * @param int $project_id       project ID to fetch status for
     * @return array|bool           data OR false
     */
    public function getStatuses($project_id) {
        $method = 'projects/' . intval($project_id) . '/statuses';
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_statuses.json");
        }
        return $data;
    }


    /**
     * Get all data related to a particular Status.
     * https://gathercontent.com/developers/projects/get-project-status/
     *
     * @param int $project_id       project ID from which we get status info
     * @param int $status_id        status ID to get info for
     * @return array|bool           data OR false
     */
    public function getStatus($project_id, $status_id) {
        $method = 'projects/' . intval($project_id) . '/statuses/' . intval($status_id);
        $data = $this->gcAPI->readAPI($method);
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_status_{$status_id}.json");
        }
        return $data;
    }


    /**
     * Retrieve all files belonging to a particular Project
     * https://gathercontent.com/support/developer-api/ see 5.Files
     *
     * @param int $project_id       project ID for which we get files info
     * @return array|bool           data OR false
     */
    public function getFilesByProject($project_id) {
        $data = $this->gcPluginAPI->readAPI('get_files_by_project', array('id' => $project_id), 'files');
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_files.json");
        }
        return $data;
    }


    /**
     * Retrieve all files belonging to a particular Item
     * https://gathercontent.com/support/developer-api/ see 5.Files
     *
     * @param int $item_id          item ID for which we get files info
     * @return array|bool           data OR false
     */
    public function getFilesByItem($item_id) {
        $data = $this->gcPluginAPI->readAPI('get_files_by_page', array('id' => $item_id), 'files');
        if ($data && $this->gcConfig->save_json_files) {
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "item_{$item_id}_files.json");
        }
        return $data;
    }


    /**
     * Retrieve file belonging to a particular Item assigned to a particular field
     * https://gathercontent.com/support/developer-api/ see 5.Files
     *
     * @param int $item_id          item ID for which we get the file info
     * @param string $field_id      field ID to which is the file assigned to
     * @return array|bool           data OR false
     */
    public function getFileByItemAndField($item_id, $field_id) {
        $data = $this->gcPluginAPI->readAPI('get_files_by_page', array('id' => $item_id), 'files');
        if ($data && is_array($data)) {
            foreach ($data as $file) {
                if ($file['field'] === $field_id) {
                    if ($this->gcConfig->save_json_files) {
                        SSGatherContentTools::saveDataInJSON($file, $this->gcConfig->assets_subfolder_json, "item_{$item_id}_field_{$field_id}_file.json");
                    }
                    return $file;
                }
            }
        }
        return false; // not found, not array or upstream returned false
    }


    /**
     * Get all data related to a particular File, store it prefixed with Project's ID
     * https://gathercontent.com/support/developer-api/ see 5.Files
     *
     * @param int $file_id
     * @return array|bool
     */
    public function getFileForProject($file_id) {
        $data = $this->gcPluginAPI->readAPI('get_file', array('id' => $file_id), 'file');
        if ($data && $this->gcConfig->save_json_files) {
            $project_id = $data['project_id'];
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "project_{$project_id}_file_{$file_id}.json");
        }
        return $data;
    }


    /**
     * Get all data related to a particular File, store it prefixed with Item's ID
     * https://gathercontent.com/support/developer-api/ see 5.Files
     *
     * @param int $file_id
     * @return array|bool
     */
    public function getFileForItem($file_id) {
        $data = $this->gcPluginAPI->readAPI('get_file', array('id' => $file_id), 'file');
        if ($data && $this->gcConfig->save_json_files) {
            $item_id = $data['page_id'];
            SSGatherContentTools::saveDataInJSON($data, $this->gcConfig->assets_subfolder_json, "item_{$item_id}_file_{$file_id}.json");
        }
        return $data;
    }


    /**
     * Backup information about the current logged in GC user
     *
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupMe($data = null) {
        if ($data === null) {
            $data = $this->gcAPI->readAPI('me');
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, 'me.json');
     }


    /**
     * Backup list of all Accounts associated with the current logged in GC user
     *
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupAccounts($data = null) {
        if ($data === null) {
            $data = $this->gcAPI->readAPI('accounts');
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, 'accounts.json');
    }


    /**
     * Backup list of all Projects associated with the current logged in GC user
     *
     * @param int $account_id       account ID to fetch projects for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupProjects($account_id, $data = null) {
        if ($data === null) {
            $method = 'projects?account_id=' . intval($account_id);
            $data = $this->gcAPI->readAPI($method);
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "account_{$account_id}_projects.json");
    }


    /**
     * Backup list of all Projects associated with the current logged in GC user
     *
     * @param int $project_id       project ID
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupProject($project_id, $data = null) {
        if ($data === null) {
            $method = 'projects/' . intval($project_id);
            $data = $this->gcAPI->readAPI($method);
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}.json");
    }


    /**
     * Backup list of all Items for particular Project.
     *
     * @param int $project_id       project ID to fetch items for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupItems($project_id, $data = null) {
        if ($data === null) {
            $method = 'items?project_id=' . intval($project_id);
            $data = $this->gcAPI->readAPI($method);
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_items.json");
    }


    /**
     * Backup all data related to a particular Item.
     *
     * @param int $item_id          item ID
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupItem($item_id, $data = null) {
        if ($data === null) {
            $method = 'items/' . intval($item_id);
            $data = $this->gcAPI->readAPI($method);
        }
        if ($data) {
            $project_id = $data['project_id'];
            return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_item_{$item_id}.json");
        } else {
            return false;
        }
    }


    /**
     * Backup list of all Templates associated with particular Project.
     *
     * @param int $project_id       project ID to fetch templates for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupTemplates($project_id, $data = null) {
        if ($data === null) {
            $method = 'templates?project_id=' . intval($project_id);
            $data = $this->gcAPI->readAPI($method);
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_templates.json");
    }


    /**
     * Backup all data related to a particular Template.
     *
     * @param int $template_id      template ID
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupTemplate($template_id, $data = null) {
        if ($data === null) {
            $method = 'templates/' . intval($template_id);
            $data = $this->gcAPI->readAPI($method);
        }
        if ($data) {
            $project_id = $data['project_id'];
            return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_template_{$template_id}.json");
        } else {
            return false;
        }
    }


    /**
     * Backup list of all Statuses associated with a particular Project.
     *
     * @param int $project_id       project ID to fetch status for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupStatuses($project_id, $data = null) {
        if ($data === null) {
            $method = 'projects/' . intval($project_id) . '/statuses';
            $data = $this->gcAPI->readAPI($method);
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_statuses.json");
    }


    /**
     * Backup all data related to a particular Status.
     *
     * @param int $project_id       project ID from which we get status info
     * @param int $status_id        status ID to get info for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupStatus($project_id, $status_id, $data = null) {
        if ($data === null) {
            $method = 'projects/' . intval($project_id) . '/statuses/' . intval($status_id);
            $data = $this->gcAPI->readAPI($method);
        }
        return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_status_{$status_id}.json");
    }


    /**
     * Backup information about all files belonging to a particular Project
     *
     * @param int $project_id       project ID to get files for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupFilesByProject($project_id, $data = null) {
        if ($data === null) {
            $data = $this->gcPluginAPI->readAPI('get_files_by_project', array('id' => $project_id), 'files');
        }
        if ($data) {
            return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_files.json");
        } else {
            return false;
        }
    }


    /**
     * Backup information about all files belonging to a particular Item
     *
     * @param int $item_id          item ID to get files for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupFilesByItem($item_id, $data = null) {
        if ($data === null) {
            $data = $this->gcPluginAPI->readAPI('get_files_by_page', array('id' => $item_id), 'files');
        }
        if ($data) {
            return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "item_{$item_id}_files.json");
        } else {
            return false;
        }
    }


    /**
     * Backup data related to a particular File and reflect Item in filename
     *
     * @param int $file_id          file ID to get data for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupFileByItem($file_id, $data = null) {
        if ($data === null) {
            $data = $this->gcPluginAPI->readAPI('get_file', array('id' => $file_id), 'file');
        }
        if ($data) {
            $item_id = $data['page_id'];
            return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "item_{$item_id}_file_{$file_id}.json");
        } else {
            return false;
        }
    }


    /**
     * Backup data related to a particular File and reflect Project in filename
     *
     * @param int $file_id          file ID to get data for
     * @param mixed|null $data      data to be saved if provided, otherwise they will be fetched from the API
     * @return array|bool           'store' array as returned by getFolderAndUniqueFilename and provided data OR false in case of failure
     */
    public function backupFileByProject($file_id, $data = null) {
        if ($data === null) {
            $data = $this->gcPluginAPI->readAPI('get_file', array('id' => $file_id), 'file');
        }
        if ($data) {
            $project_id = $data['project_id'];
            return SSGatherContentTools::backupDataInJSON($data, $this->gcConfig->assets_subfolder_backup, "project_{$project_id}_file_{$file_id}.json");
        } else {
            return false;
        }
    }

}
