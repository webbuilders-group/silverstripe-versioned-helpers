<?php
/**
 * Class VersionedModifiedExtension
 *
 */
class VersionedModifiedExtension extends DataExtension {
    protected $_relations=array();
    
    private static $casting=array(
                                'IsModifiedOnStage'=>'Boolean',
                                'IsPublished'=>'Boolean'
                            );
    
    private $_originalOwnerClass=false;
    
    
    /**
     * Constructor
     * @param string $relationship ... Names of the has_many relationships to sync the version state (Overloaded)
     */
    public function __construct($relationship) {
        parent::__construct();
        
        $this->_relations=func_get_args();
    }
    
    /**
	 * Set the owner of this extension.
	 * @param Object $owner The owner object
	 * @param string $ownerBaseClass The base class that the extension is applied to; this may be the class of owner, or it may be a parent
	 */
    public function setOwner($owner, $ownerBaseClass=null) {
        parent::setOwner($owner, $ownerBaseClass);
        
        //In dev mode do validation of the relations
        if(Director::isDev() && $this->owner instanceof DataObject && $this->_originalOwnerClass===false) {
            $this->_originalOwnerClass=$this->owner->ClassName;
            
            if($this->_originalOwnerClass!=$this->owner->ClassName) {
                return;
            }
            
            foreach($this->_relations as $relation) {
                $relClass=$this->owner->hasMany($relation);
                if(!$relClass) {
                    user_error('Could not find the has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
            
                $parentField=$this->owner->getRemoteJoinField($relation, 'has_many');
                if(!$parentField) {
                    user_error('Could not find the parent relationship on has_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
            }
        }
    }
    
    /**
     * Compares current draft with live version, and returns true if these versions differ, meaning there have been unpublished changes to the draft site.
     * @param bool $modified Whether or not the parent is modified or not
     */
    public function getIsModifiedOnStage(&$modified=false) {
        if(!$modified) {
            foreach($this->_relations as $relation) {
                $relClass=$this->owner->hasMany($relation);
                if(!$relClass) {
                    continue;
                }
                
                $parentField=$this->owner->getRemoteJoinField($relation, 'has_many');
                if(!$parentField) {
                    continue;
                }
                
                //Make sure the Items all exist on both stages
                $stageItems=Versioned::get_by_stage($relClass, 'Stage')->filter($parentField, $this->owner->ID)->column('ID');
                $liveItems=Versioned::get_by_stage($relClass, 'Live')->filter($parentField, $this->owner->ID)->column('ID');
        
                $diff=array_merge(array_diff($stageItems, $liveItems), array_diff($liveItems, $stageItems));
                if(count($diff)>0) {
                    $modified=true;
                    break;
                }
                
                
                $hasModifiedMethod=singleton($relClass)->hasMethod('getIsModifiedOnStage');
                
                //Check each item to see if it is modified
                if(!$modified && count($stageItems)>0) {
                    $baseClass=ClassInfo::baseDataClass($relClass);
                    foreach($stageItems as $itemID) {
                        if($hasModifiedMethod) {
                            $item=Versioned::get_one_by_stage($baseClass, 'Stage', '"ID"='.intval($itemID));
                            if(!empty($item) && $item!==false && $item->exists()) {
                                $modified=$item->getIsModifiedOnStage($modified);
                                if($modified) {
                                    break;
                                }
                            }
                        }else {
                            $stageVersion=Versioned::get_versionnumber_by_stage($baseClass, 'Stage', $itemID);
                            $liveVersion=Versioned::get_versionnumber_by_stage($baseClass, 'Live', $itemID);
                            
                            if($stageVersion && $stageVersion!=$liveVersion) {
                                $modified=true;
                                break;
                            }
                        }
                    }
                    
                    //Break out if modified
                    if($modified) {
                        break;
                    }
                }
            }
        }
        
        return $modified;
    }
    
    /**
     * Compare two stages to see if they're different. Only checks the version numbers, not the actual content.
     * @param string $stage1 The first stage to check.
     * @param string $stage2
     */
    public function stagesDiffer($stage1, $stage2) {
        $stagesAreEqual=true;
        
        //Check to see if the items differ
        foreach($this->_relations as $relation) {
            $relClass=$this->owner->hasMany($relation);
            if(!$relClass) {
                continue;
            }
            
            $baseClass=ClassInfo::baseDataClass($relClass);
            
            $parentField=$this->owner->getRemoteJoinField($relation, 'has_many');
            if(!$parentField) {
                continue;
            }
            
            //Make sure the Items all exist on both stages
            $stageItems=Versioned::get_by_stage($relClass, 'Stage')->filter($parentField, $this->owner->ID)->column('ID');
            $liveItems=Versioned::get_by_stage($relClass, 'Live')->filter($parentField, $this->owner->ID)->column('ID');
            
            $diff=array_merge(array_diff($stageItems, $liveItems), array_diff($liveItems, $stageItems));
            if(count($diff)>0) {
                $stagesAreEqual=false;
            }
            
            //Check each item to see if it is modified
            if($stagesAreEqual) {
                $hasDifferMethod=singleton($relClass)->hasMethod('stagesDiffer');
                    
                $table1=($stage1=='Stage' ? $relClass:$relClass.'_'.$stage1);
                $table2=($stage2=='Stage' ? $relClass:$relClass.'_'.$stage2);
                foreach($stageItems as $itemID) {
                    if($hasDifferMethod) {
                        $item=Versioned::get_one_by_stage($baseClass, 'Stage', '"ID"='.intval($itemID));
                        if(!empty($item) && $item!==false && $item->exists()) {
                            $stagesAreEqual=!$item->stagesDiffer($stage1, $stage2);
                        }else {
                            $stagesAreEqual=false;
                        }
                    }else {
                        $stagesAreEqual=DB::prepared_query(
                                                        "SELECT CASE WHEN \"$table1\".\"Version\"=\"$table2\".\"Version\" THEN 1 ELSE 0 END
                                                        FROM \"$table1\" INNER JOIN \"$table2\" ON \"$table1\".\"ID\" = \"$table2\".\"ID\"
                                                        AND \"$table1\".\"ID\" = ?",
                                                        array($itemID)
                                                    )->value();
                    }
                    
                    if(!$stagesAreEqual) {
                        break;
                    }
                }
            }
            
            if(!$stagesAreEqual) {
                break;
            }
        }
        
        return !$stagesAreEqual;
    }
    
    /**
     * Return the base table - the class that directly extends DataObject.
     * @param string $stage Override the stage used
     * @return string
     */
    protected function baseTable($stage=null) {
        $tableClasses=ClassInfo::dataClassesFor($this->owner->class);
        $baseClass=array_shift($tableClasses);
        
        if(!$stage || $stage==$this->owner->getDefaultStage()) {
            return $baseClass;
        }
        
        return $baseClass."_$stage";
    }
}
?>