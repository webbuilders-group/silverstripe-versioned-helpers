<?php
/**
 * Class StagedManyManyRelationExtension
 *
 */
class StagedManyManyRelationExtension extends DataExtension {
    protected $_relations=array();
    protected $_joinTables=array();
    
    
    /**
     * Constructor
     * @param string $relationship ... Names of the many_many relationships to sync the version state (Overloaded)
     */
    public function __construct($relationship) {
        parent::__construct();
        
        $this->_relations=func_get_args();
    }
    
    /**
     * Set the owner of this extension
     * @param Object $owner The owner object
     * @param string $ownerBaseClass The base class that the extension is applied to; this may be the class of owner, or it may be a parent.  For example, if Versioned was applied to SiteTree, and then a Page object was instantiated, $owner would be a Page object, but $ownerBaseClass would be 'SiteTree'.
     */
    public function setOwner($owner, $ownerBaseClass=null) {
        parent::setOwner($owner, $ownerBaseClass);
        
        if($this->owner instanceof DataObject) {
            $this->_joinTables=array_map(array($this, 'concatOwnerTable'), $this->_relations);
        }
    }
    
    /**
     * Compares current draft with live version, and returns true if these versions differ, meaning there have been unpublished changes to the draft site.
     * @param bool $modified Whether or not the parent is modified or not
     */
    public function getIsModifiedOnStage(&$modified=false) {
        if(!$modified) {
            foreach($this->_relations as $relation) {
                $relationLive=$relation.'_Live';
                
                $relClass=$this->owner->manyMany($relation);
                if(!$relClass) {
                    user_error('Could not find the many_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
                
                $relLiveClass=$this->owner->manyMany($relationLive);
                if(!$relLiveClass || $relClass[1]!=$relLiveClass[1]) {
                    user_error('Could not find the many_many relationship "'.$relationLive.'" on "'.$this->owner->class.'" or the class does not match.', E_USER_ERROR);
                }
                
                //Make sure the Items all exist on both stages
                $stageItems=$this->owner->$relation()->column('ID');
                $liveItems=$this->owner->$relationLive()->column('ID');
                
                $diff=array_merge(array_diff($stageItems, $liveItems), array_diff($liveItems, $stageItems));
                if(count($diff)>0) {
                    $modified=true;
                    break;
                }
            }
        }
        
        return $modified;
    }
    
    /**
     * Compare two stages to see if they're different. Only checks the ID's match, not the actual content.
     * @param string $stage1 The first stage to check.
     * @param string $stage2
     */
    public function stagesDiffer($stage1, $stage2) {
        $table1=$this->baseTable($stage1);
        $table2=$this->baseTable($stage2);
        
        if(!is_numeric($this->owner->ID)) {
            return true;
        }
        
        // We test for equality - if one of the versions doesn't exist, this will be false.
        
        $stagesAreEqual=DB::prepared_query(
                                            "SELECT CASE WHEN \"$table1\".\"Version\"=\"$table2\".\"Version\" THEN 1 ELSE 0 END
                                             FROM \"$table1\" INNER JOIN \"$table2\" ON \"$table1\".\"ID\" = \"$table2\".\"ID\"
                                             AND \"$table1\".\"ID\" = ?",
                                            array($this->owner->ID)
                                        )->value();
        
        
        //Check to see if the items differ
        if($stagesAreEqual) {
            foreach($this->_relations as $relation) {
                $relationLive=$relation.'_Live';
                
                $relClass=$this->owner->manyMany($relation);
                if(!$relClass) {
                    user_error('Could not find the many_many relationship "'.$relation.'" on "'.$this->owner->class.'"', E_USER_ERROR);
                }
                
                $relLiveClass=$this->owner->manyMany($relationLive);
                if(!$relLiveClass || $relClass[1]!=$relLiveClass[1]) {
                    user_error('Could not find the many_many relationship "'.$relationLive.'" on "'.$this->owner->class.'" or the class does not match.', E_USER_ERROR);
                }
                
                
                //Make sure the Items all exist on both stages
                $stageItems=$this->owner->$relation()->column('ID');
                $liveItems=$this->owner->$relationLive()->column('ID');
                
                $diff=array_merge(array_diff($stageItems, $liveItems), array_diff($liveItems, $stageItems));
                if(count($diff)>0) {
                    $stagesAreEqual=false;
                }
                
                if(!$stagesAreEqual) {
                    break;
                }
            }
        }
        
        return !$stagesAreEqual;
    }
    
    /**
     * Handles versioning of the many_many relations
     * @param ManyManyList $list Many Many List to replace
     */
    public function updateManyManyComponents(ManyManyList &$list) {
        if(Versioned::current_stage()=='Live' && in_array($list->getJoinTable(), $this->_joinTables)) {
            $list=ManyManyList::create($list->dataClass(), $list->getJoinTable().'_Live', $list->getLocalKey(), $list->getForeignKey(), $list->getExtraFields());
        }
    }
    
    /**
     * Handles publishing the versioned many_many relationships
     * @param string $fromStage Stage to publish from
     * @param string $toStage Stage to publish to
     * @param bool $createNewVersion Whether to create a new version or not
     */
    public function onBeforeVersionedPublish($fromStage, $toStage, $createNewVersion) {
        foreach($this->_relations as $relName) {
            $relLiveName=$relName.'_Live';
            
            if($fromStage=='Stage' && $toStage=='Live') {
                $this->owner->$relLiveName()->removeAll();
                
                $ids=$this->owner->$relName()->column('ID');
                if(count($ids)>0) {
                    foreach($ids as $id) {
                        $this->owner->$relLiveName()->add($id, $this->owner->$relName()->getExtraData($relName, $id));
                    }
                }
            }else if($fromStage=='Live' && $toStage=='Stage') {
                $this->owner->$relName()->removeAll();
                
                $ids=$this->owner->$relLiveName()->column('ID');
                if(count($ids)>0) {
                    foreach($ids as $id) {
                        $this->owner->$relName()->add($id, $this->owner->$relLiveName()->getExtraData($relName, $id));
                    }
                }
            }
        }
    }
    
    /**
     * Concatenates on the class name of the owner
     * @param string $item Relationship name to add to
     * @return string
     */
    protected function concatOwnerTable($item) {
        return $this->owner->class.'_'.$item;
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