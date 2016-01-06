<?php

class GatherContentBackupTask extends BuildTask {

    protected $title = 'GatherContent backup';
    protected $description = 'Backup content from GatherContent, cloud based content planning and organising platform (<a href="https://gathercontent.com/">https://gathercontent.com/</a>)';
    protected $enabled = true;

    private $gc;

    public function __construct() {
        $this->gc = new SSGatherContent();
    }

    function run($request) {
        echo '<pre><br>start time: ' . date('d/m/Y H:i:s') . '<br>' . PHP_EOL;

        $this->gc->backupContentFromGatherContent();

        echo '<br>end time: ' . date('d/m/Y H:i:s') . '<br>' . PHP_EOL;
    }

}
