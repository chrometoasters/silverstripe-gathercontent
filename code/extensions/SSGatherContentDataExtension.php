<?php

/**
 * Class GatherContentDataExtension
 *
 * Data extension to store some GatherContent related data with CMS object.
 *
 * It's recommended to add this extension to all page types and data objects that could be potentially
 * created during the import of GatherContent prepared content.
 *
 * By default, GC_ItemID is used to save unique ID of the respective item in GatherContent and it's used
 * to determine, whether this item has been previously imported/updated from GC, unless different unique field
 * is configured using the gc_unique_identifier config option.
 *
 * It also holds dates when the item was created and updated from GatherContent, if applicable.
 *
 * Properties and methods are prefixed with GC so it's obvious they relate to GatherContent when used somewhere else.
 *
 */
class SSGatherContentDataExtension extends DataExtension {

    /**
     * Extension added fields
     *
     * @var array
     */
    private static $db = array(
        'GC_ID'                 => 'Varchar(100)',
        'GC_ParentID'           => 'Varchar(100)',
        'GC_DateCreated'        => 'SS_DateTime',
        'GC_DateLastUpdated'    => 'SS_DateTime',
        'GC_Log'                => 'Text',
    );


    /**
     * Add CMS fields only if the item was previously updated from GatherContent
     *
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields) {

        // only add the tab and fields when the item was updated from GatherContent and it's a descendant of SiteTree
        if ($this->owner->GC_LastUpdated && $this->owner instanceof SiteTree) {

            // get tab
            $fields->findOrMakeTab('Root.GatherContent', 'GatherContent info');

            // add fields
            $fields->addFieldsToTab('Root.GatherContent', array(
                ReadonlyField::create('GC_ID', 'Item ID'),
                ReadonlyField::create('GC_ParentID', 'Parent item ID'),
                ReadonlyField::create('GC_DateCreated', 'Date imported from GatherContent'),
                ReadonlyField::create('GC_DateLastUpdated', 'Date updated from GatherContent'),
                TextareaField::create('GC_Log', 'Log')->setRows(10)->performReadonlyTransformation(),
            ));

        } else {
        // hide fields if not SiteTree
            $fields->removeByName('GC_ID');
            $fields->removeByName('GC_ParentID');
            $fields->removeByName('GC_DateCreated');
            $fields->removeByName('GC_DateLastUpdated');
            $fields->removeByName('GC_Log');
        }

    }


    /**
     * Store GatherContent item's ID
     *
     * @param int|string $id        ID
     */
    public function GC_storeItemID($id) {
        $this->owner->GC_ID = $id;
    }


    /**
     * Store GatherContent parent item's ID
     *
     * @param int|string $id        ID
     */
    public function GC_storeParentItemID($id) {
        $this->owner->GC_ParentID = $id;
    }


    /**
     * Store current or provided date as date when the item was created from GatherContent, if the date is not set already
     *
     * @param string|null $date     date in acceptable format for SS_DateTime (NZ format or ISO 8601 formatted date and time [Y-m-d H:i:s])
     */
    public function GC_storeDateCreated($date = null) {
        if (!$date) {
            $date = SS_Datetime::now();
        }

        if (!$this->owner->GC_DateCreated) {
            $this->owner->GC_DateCreated = $date;
        }
    }


    /**
     * Manually store current or provided date as date when the item was updated from GatherContent
     * Not likely to be used much as onBeforeWrite is used to save LastUpdated date
     *
     * @param string|null $date     date in acceptable format for SS_DateTime (NZ format or ISO 8601 formatted date and time [Y-m-d H:i:s])
     */
    public function GC_storeDateLastUpdated($date = null) {
        if (!$date) {
            $date = SS_Datetime::now();
        }

        $this->owner->GC_DateLastUpdated = $date;
    }


    /**
     * Add or rewrite log for the item
     *
     * @param $log                  text to be added to the log or to replace the log (based on next param)
     * @param bool|true $append     whether to append (true) to the log or rewrite (false) the log
     */
    public function GC_storeLog($log, $append = true) {
        if ($append) {
            $this->owner->GC_Log = $this->owner->GC_Log . PHP_EOL . $log;
        } else {
            $this->owner->GC_Log = $log;
        }
    }


    /**
     * Wrapper function to store ID, ParentID and created and last updated dates in one go
     *
     * @param int|string $id                    ID
     * @param int|string|null $parentId         Parent ID
     * @param string|null $dateCreated          'created' date in acceptable format for SS_DateTime (NZ format or ISO 8601 formatted date and time [Y-m-d H:i:s])
     * @param string|null $dateLastUpdated      'last updated' date in acceptable format for SS_DateTime (NZ format or ISO 8601 formatted date and time [Y-m-d H:i:s])
     */
    public function GC_storeAllInfo($id, $parentId = null, $dateCreated = null, $dateLastUpdated = null) {
        $this->owner->GC_storeItemID($id);
        $this->owner->GC_storeParentItemID($parentId);
        $this->owner->GC_storeDateCreated($dateCreated);
        $this->owner->GC_storeDateLastUpdated($dateLastUpdated);
    }


}
