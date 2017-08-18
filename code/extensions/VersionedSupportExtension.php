<?php
/**
 * Class VersionedSupportExtension
 *
 */
class VersionedSupportExtension extends DataExtension {
    private static $casting=array(
                                'IsModifiedOnStage'=>'Boolean',
                                'IsPublished'=>'Boolean',
                                'IsDeletedFromStage'=>'Boolean',
                                'ExistsOnLive'=>'Boolean'
                            );
    
    
	/**
	 * Compares current draft with live version, and returns true if these versions differ, meaning there have been unpublished changes to the draft site.
     * @return bool
     */
    public function getIsModifiedOnStage() {
        // New unsaved pages could be never be published
        if(!$this->owner->exists()) {
            return false;
        }
        
        $stageVersion=Versioned::get_versionnumber_by_stage($this->owner->class, 'Stage', $this->owner->ID);
        $liveVersion=Versioned::get_versionnumber_by_stage($this->owner->class, 'Live', $this->owner->ID);
        
        $isModified=($stageVersion && $stageVersion!=$liveVersion);
        
        return $isModified;
    }
    
    /**
     * Compares current draft with live version, and returns TRUE if no draft version of this page exists, but the page is still published (after triggering "Delete from draft site" in the CMS).
     * @return bool
     */
    public function getIsDeletedFromStage() {
        if(!$this->owner->ID) {
            return true;
        }
        
        if(!$this->owner->exists()) {
            return false;
        }
        
        $stageVersion=Versioned::get_versionnumber_by_stage($this->owner->class, 'Stage', $this->owner->ID);
        
        // Return true for both completely deleted pages and for pages just deleted from stage.
        return !($stageVersion);
    }
    
    /**
     * Return true if this page exists on the live site
     */
    public function getExistsOnLive() {
        return (bool)Versioned::get_versionnumber_by_stage($this->owner->class, 'Live', $this->owner->ID);
    }
}
?>