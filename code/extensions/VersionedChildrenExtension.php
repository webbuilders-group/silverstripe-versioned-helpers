<?php
/**
 * Class VersionedChildrenExtension
 *
 */
class VersionedChildrenExtension extends VersionedModifiedExtension {
    /**
     * Margin of error in minutes between the last edit date of the parent and the children
     * @config VersionedChildrenExtension.archived_error_margin
     * @var int
     */
    private static $archived_error_margin=1;
    
    
    /**
     * Publishes the relations
     */
    public function onAfterPublish() {
        foreach($this->_relations as $relation) {
            $relClass=$this->owner->hasMany($relation);
            if(!$relClass) {
                user_error('Could not find the has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
            }
            
            $parentField=$this->owner->getRemoteJoinField($relation, 'has_many');
            if(!$parentField) {
                user_error('Could not find the parent relationship on has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
            }
        
            if($this->owner->$relation()->count()>0) {
                $stageItems=$this->owner->$relation()->column('ID');
                foreach($this->owner->$relation() as $item) {
                    if($item->hasMethod('doPublish')) {
                        $item->doPublish('Stage', 'Live');
                    }else {
                        $item->publish('Stage', 'Live');
                    }
                }
                
                //Remove orphaned live Items
                $liveItemsNotStaged=Versioned::get_by_stage($relClass, 'Live')->filter($parentField, $this->owner->ID)->filter('ID:not', $stageItems);
                if($liveItemsNotStaged->count()>0) {
                    $oldMode=Versioned::get_reading_mode();
                    Versioned::reading_stage('Stage');
                    
                    foreach($liveItemsNotStaged as $item) {
                        $item->deleteFromStage('Live');
                    }
                    
                    Versioned::set_reading_mode($oldMode);
                }
            }
        }
    }
    
    /**
     * Unpublishes the relations from the live site
     */
    public function onAfterUnpublish() {
        $oldMode=Versioned::get_reading_mode();
        Versioned::reading_stage('Stage');
        
        
        foreach($this->_relations as $relation) {
            $relClass=$this->owner->hasMany($relation);
            if(!$relClass) {
                user_error('Could not find the has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
            }
            
            if($this->owner->$relation()->count()>0) {
                foreach($this->owner->$relation() as $item) {
                    if($item->hasMethod('doUnpublish')) {
                        $item->doUnpublish();
                    }else {
                        $item->deleteFromStage('Live');
                    }
                }
            }
        }
        
        Versioned::set_reading_mode($oldMode);
    }
    
    /**
     * Revert the draft changes: replace the draft content with the content on live
     */
    public function onAfterRevertToLive() {
        $origMode=Versioned::get_reading_mode();
        
        //Switch to the live site
        Versioned::reading_stage('Live');
        
        
        foreach($this->_relations as $relation) {
            $relClass=$this->owner->hasMany($relation);
            if(!$relClass) {
                user_error('Could not find the has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
            }
            
            
            //Publish all live questions to the staging site
            if($this->owner->$relation()->count()>0) {
                $liveItems=$this->owner->$relation()->column('ID');
                foreach($this->owner->$relation() as $item) {
                    if($item->hasMethod('doRevertToLive')) {
                        $item->doRevertToLive();
                    }else {
                        $item->publish('Live', 'Stage', false);
                    }
                    
                    $item->writeWithoutVersion();
                }
                
                //Ensure we're working with the stage site
                Versioned::reading_stage('Stage');
                
                //Remove all stage questions not on the live site
                $missingItems=$this->owner->$relation()->filter('ID:not', $liveItems);
                if($missingItems->count()>0) {
                    foreach($missingItems as $item) {
                        $item->delete();
                    }
                }
            }else {
                //Remove all questions from staging
                //Ensure we're working with the stage site
                Versioned::reading_stage('Stage');
                
                //Remove all stage questions
                $missingItems=$this->owner->$relation();
                if($this->owner->$relation()->count()>0) {
                    foreach($this->owner->$relation() as $item) {
                        $item->delete();
                    }
                }
            }
        }
        
        //Restore Versioned
        Versioned::reading_stage($origMode);
    }
    
    /**
     * Roll the draft version of this page to match the published page.
     * Caution: Doesn't overwrite the object properties with the rolled back version.
     * @param int|string $version Either the string 'Live' or a version number
     */
    public function onAfterRollback($version) {
        //Rollback the questions
        if($version=='Live') {
            $origMode=Versioned::get_reading_mode();
            
            //Switch to the live site
            Versioned::reading_stage('Live');
            
            
            foreach($this->_relations as $relation) {
                $relClass=$this->owner->hasMany($relation);
                if(!$relClass) {
                    user_error('Could not find the has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
                
                //Publish all live questions to the staging site
                if($this->owner->$relation()->count()>0) {
                    $liveItems=$this->owner->$relation()->column('ID');
                    foreach($this->owner->$relation() as $item) {
                        if($item->hasMethod('doPublish')) {
                            $item->doPublish('Live', 'Stage', false);
                        }else {
                            $item->publish('Live', 'Stage', false);
                        }
                        $item->writeWithoutVersion();
                    }
                    
                    //Ensure we're working with the stage site
                    Versioned::reading_stage('Stage');
                    
                    //Remove all stage questions not on the live site
                    $missingItems=$this->owner->$relation()->filter('ID:not', $liveItems);
                    if($missingItems->count()>0) {
                        foreach($missingItems as $item) {
                            $item->delete();
                        }
                    }
                }else { //Remove all questions from staging
                    //Ensure we're working with the stage site
                    Versioned::reading_stage('Stage');
                    
                    //Remove all stage questions
                    if($this->owner->$relation()->count()>0) {
                        foreach($this->owner->$relation() as $item) {
                            $item->delete();
                        }
                    }
                }
            }
            
            //Restore Versioned
            Versioned::reading_stage($origMode);
        }else if(is_numeric($version)) {
            $origStage=Versioned::current_stage();
            
            
            //Ensure we're working with the stage site
            Versioned::reading_stage('Stage');
            
            
            foreach($this->_relations as $relation) {
                $relClass=$this->owner->hasMany($relation);
                if(!$relClass) {
                    user_error('Could not find the has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
                
                $parentField=$this->owner->getRemoteJoinField($relation, 'has_many');
                if(!$parentField) {
                    user_error('Could not find the parent relationship on has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
                
                //Remove all stage items
                if($this->owner->$relation()->count()>0) {
                    foreach($this->owner->$relation() as $item) {
                        $item->delete();
                    }
                }
                
                
                //Restore from the archive
                $versionObject=Versioned::get_version(ClassInfo::baseDataClass($this->owner->class), $this->owner->ID, $version);
                
                $items=$this->getArchivedItems($relation, $relClass, $parentField, $versionObject->LastEdited);
                foreach($items as $item) {
                    $item->publish($item->Version, 'Stage');
                }
            }
            
            
            //Restore Versioned
            Versioned::reading_stage($origStage);
        }
    }
    
    /**
     * Gets the latest items at the head of the versions for the parent object
     * @param string $relation Name of the relationship
     * @param string $baseClassName Base Class Name of the relationship object
     * @param string $parentField Parent Field Name of the relationship object
     * @param string $lastEdited The last edit date of the parent object
     * @return ArrayList|DataObject[]
     */
    protected function getArchivedItems($relation, $baseClassName, $parentField, $lastEdited=false) {
        $startDate=date('Y-m-d H:i:s', strtotime(($lastEdited===false ? $this->LastEdited:$lastEdited).' -'.Config::inst()->get('VersionedChildrenExtension', 'archived_error_margin').' minutes'));
        $endDate=date('Y-m-d H:i:s', strtotime(($lastEdited===false ? $this->LastEdited:$lastEdited).' +'.Config::inst()->get('VersionedChildrenExtension', 'archived_error_margin').' minutes'));
        
        $query=SQLSelect::create()
                        ->setSelect(array(
                                        'RecordID'=>'MAX("'.$baseClassName.'_versions"."RecordID")'
                                    ))
                        ->setFrom('"'.$baseClassName.'_versions"')
                        ->addWhere(array(
                                        '"'.$baseClassName.'_versions"."'.$parentField.'"= ?'=>$this->owner->ID,
                                        '"'.$baseClassName.'_versions"."LastEdited">= ?'=>$startDate,
                                        '"'.$baseClassName.'_versions"."LastEdited"<= ?'=>$endDate
                                    ))
                        ->addGroupBy('"'.$baseClassName.'_versions"."RecordID"');
        
        
        $result=$query->execute();
        if($result->numRecords()>0) {
            return $this->owner->$relation()
                                            ->setDataQueryParam('Versioned.mode', 'archive')
                                            ->setDataQueryParam('Versioned.date', $endDate)
                                            ->filter('RecordID', $result->column('RecordID'))
                                            ->filter('LastEdited:GreaterThanOrEqual', $startDate)
                                            ->filter('LastEdited:LessThanOrEqual', $endDate);
        }
        
        return $this->owner->$relation()->filter('ID', 0);
    }
}
?>