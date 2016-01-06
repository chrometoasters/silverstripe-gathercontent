<?php

class GatherContentImportTask extends BuildTask {

    protected $title = 'GatherContent import';
    protected $description = 'Import content from GatherContent, cloud based content planning and organising platform (<a href="https://gathercontent.com/">https://gathercontent.com/</a>)';
    protected $enabled = true;

    private $gc;

    public function __construct() {
        $this->gc = new SSGatherContent();
    }

    function run($request) {
        echo '<pre><br>start time: ' . date('d/m/Y H:i:s') . '<br>' . PHP_EOL;

        $this->gc->loadContentFromGatherContent();

        echo '<br>end time: ' . date('d/m/Y H:i:s') . '<br>' . PHP_EOL;
    }

}
