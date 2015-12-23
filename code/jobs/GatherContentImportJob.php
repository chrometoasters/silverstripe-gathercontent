<?php

class GatherContentImportJob extends AbstractQueuedJob implements QueuedJob {

    private $gc;

    public function __construct() {
        $this->gc = new SSGatherContent();
    }

    public function getTitle() {
        return "GatherContent import";
    }

    public function getJobType() {
        $this->totalSteps = 'Lots';
        return QueuedJob::QUEUED;
    }


    public function setup() {
        $this->totalSteps = 1;
    }

    public function process() {
        $this->gc->loadContentFromGatherContent();

        $this->isComplete = true;
        return;
    }

}
