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
        return $data;
    }


    /**
     * Retrieve a list of all Accounts associated with the current logged in GC user
     * https://gathercontent.com/developers/accounts/get-accounts/
     */
    public function getAccounts() {
        $data = $this->gcAPI->readAPI('accounts');
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
        return $data;
    }


    /**
     * Retrieve all files belonging to a particular Project
     * https://gathercontent.com/support/developer-api/ see 5.Files
     */
    public function getFilesByProject($project_id) {
        $data = $this->gcPluginAPI->readAPI('get_files_by_project', ['id' => $project_id], 'files');
        return $data;
    }


    /**
     * Retrieve all files belonging to a particular Item
     * https://gathercontent.com/support/developer-api/ see 5.Files
     */
    public function getFilesByPage($page_id) {
        $data = $this->gcPluginAPI->readAPI('get_files_by_page', ['id' => $page_id], 'files');
        return $data;
    }


    /**
     * Get all data related to a particular File.
     * https://gathercontent.com/support/developer-api/ see 5.Files
     */
    public function getFile($file_id) {
        $data = $this->gcPluginAPI->readAPI('get_file', ['id' => $file_id], 'file');
        return $data;
    }

}
