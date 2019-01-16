<?php
namespace WebbuildersGroup\VersionedHelpers\Extensions;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\RelationList;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\Versioned\Versioned;


/**
 * Class \WebbuildersGroup\VersionedHelpers\Extensions\StagedManyManyRelationExtension
 *
 */
class StagedManyManyRelationExtension extends DataExtension {
    private static $disabled=false;
    
    protected $_joinTables=array();
    
    /**
     * Constructor
     * @param string $relationship ... Names of the many_many relationships to sync the version state (Overloaded)
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Set the owner of this extension
     * @param Object $owner The owner object
     * @param string $ownerBaseClass The base class that the extension is applied to; this may be the class of owner, or it may be a parent.  For example, if Versioned was applied to SiteTree, and then a Page object was instantiated, $owner would be a Page object, but $ownerBaseClass would be 'SiteTree'.
     */
    public function setOwner($owner, $ownerBaseClass=null) {
        parent::setOwner($owner, $ownerBaseClass);
        
        if($this->owner instanceof DataObject) {
            $ownerClass=$this->owner->ClassName;
            $relations=$this->owner->config()->get('staged_many_many');
            if(!is_array($relations) || count($relations)==0) {
                return;
            }
            
            foreach($relations as $relation) {
                $details=$this->owner->getSchema()->manyManyComponent($ownerClass, $relation);
                if($details) {
                    $this->_joinTables[]=$details['join'];
                }else {
                    user_error('Could not find the many_many relationship "'.$relation.'" on "'.$ownerClass.'"', E_USER_ERROR);
                }
            }
        }
    }
    
    /**
     * Compare two stages to see if they're different. Only checks the ID's match, not the actual content.
     * @return bool
     */
    public function stagesDiffer() {
        self::$disabled=true;
        
        $stagesAreEqual=true;
        
        $ownerClass=$this->owner->ClassName;
        $relations=$this->owner->config()->get('staged_many_many');
        if(!is_array($relations) || count($relations)==0) {
            return;
        }
        
        if(!is_numeric($this->owner->ID)) {
            self::$disabled=false;
            
            return true;
        }
        
        foreach($relations as $relation) {
            $relationLive=$relation.'_Live';
            
            $relClass=$this->owner->getSchema()->manyManyComponent($ownerClass, $relation);
            if(!$relClass) {
                user_error('Could not find the many_many relationship "'.$relation.'" on "'.$ownerClass.'"', E_USER_ERROR);
            }
            
            $relLiveClass=$this->owner->getSchema()->manyManyComponent($ownerClass, $relationLive);
            if(!$relLiveClass || $relClass['childClass']!=$relLiveClass['childClass']) {
                user_error('Could not find the many_many relationship "'.$relationLive.'" on "'.$ownerClass.'" or the class does not match.', E_USER_ERROR);
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
        
        self::$disabled=false;
        
        return !$stagesAreEqual;
    }
    
    /**
     * Handles versioning of the many_many relations
     * @param RelationList $list Many Many List to replace
     */
    public function updateManyManyComponents(RelationList &$list) {
        if(self::$disabled==false && $list instanceof ManyManyList && Versioned::get_stage()==Versioned::LIVE && in_array($list->getJoinTable(), $this->_joinTables)) {
            $list=ManyManyList::create($list->dataClass(), $list->getJoinTable().'_Live', $list->getLocalKey(), $list->getForeignKey(), $list->getExtraFields());
        }
    }
    
    /**
     * Handles publishing the versioned many_many relationships
     * @param DataObjectInterface $original Original object being published
     */
    public function onAfterPublish(DataObjectInterface $original=null) {
        self::$disabled=true;
        
        $relations=$this->owner->config()->get('staged_many_many');
        if(!is_array($relations) || count($relations)==0) {
            return;
        }
        
        foreach($relations as $relName) {
            $relLiveName=$relName.'_Live';
            
            $relClass=$this->owner->getSchema()->manyManyComponent($this->owner->ClassName, $relLiveName);
            if(!$relClass) {
                continue;
            }
            
            $list=$this->owner->$relLiveName();
            
            //Remove all from this relationship
            $filter=$this->foreignIDFilter($list);
            if(is_array($filter)) {
                //Delete regardless of whether the original objects exist or not
                $delete=new SQLDelete();
                $delete->setFrom('"'.$list->getJoinTable().'"');
                $delete->addWhere($filter);
                $delete->execute();
            }
            
            $ids=$this->owner->$relName()->column('ID');
            if(count($ids)>0) {
                foreach($ids as $id) {
                    $list->add($id, $this->owner->$relName()->getExtraData($relName, $id));
                }
            }
        }
        
        self::$disabled=false;
    }
    
    /**
     * Handles reverting the relationship to live
     * @param string|int $version Version number of stage name
     * @see StagedManyManyRelationExtension::onAfterRollbackRecursive()
     */
    public function onAfterRollback($version) {
        $this->onAfterRollbackRecursive($version);
    }
    
    /**
     * Handles reverting the relationship to live
     * @param string|int $version Version number of stage name
     */
    public function onAfterRollbackRecursive($version) {
        if($version==Versioned::LIVE) {
            self::$disabled=true;
            
            $relations=$this->owner->config()->get('staged_many_many');
            if(!is_array($relations) || count($relations)==0) {
                return;
            }
            
            foreach($relations as $relName) {
                $relLiveName=$relName.'_Live';
                
                $relClass=$this->owner->getSchema()->manyManyComponent($this->owner->ClassName, $relLiveName);
                if(!$relClass) {
                    continue;
                }
                
                $list=$this->owner->$relName();
                
                //Remove all from this relationship
                $filter=$this->foreignIDFilter($list);
                if(is_array($filter)) {
                    //Delete regardless of whether the original objects exist or not
                    $delete=new SQLDelete();
                    $delete->setFrom('"'.$list->getJoinTable().'"');
                    $delete->addWhere($filter);
                    $delete->execute();
                }
                
                
                $ids=$this->owner->$relLiveName()->column('ID');
                if(count($ids)>0) {
                    foreach($ids as $id) {
                        $list->add($id, $this->owner->$relLiveName()->getExtraData($relName, $id));
                    }
                }
            }
            
            self::$disabled=false;
        }
    }
    
    /**
     * Removes the items from the live relationship after unpublishing
     */
    public function onAfterUnpublish() {
        self::$disabled=true;
        
        $relations=$this->owner->config()->get('staged_many_many');
        if(!is_array($relations) || count($relations)==0) {
            return;
        }
        
        foreach($relations as $relation) {
            $relLiveName=$relation.'_Live';
            
            $relClass=$this->owner->getSchema()->manyManyComponent($this->owner->ClassName, $relLiveName);
            if(!$relClass) {
                continue;
            }
            
            //Remove all from this relationship
            $list=$this->owner->$relLiveName();
            $filter=$this->foreignIDFilter($list);
            if(!is_array($filter)) {
                continue;
            }
            
            
            //Delete regardless of whether the original objects exist or not
            $delete=new SQLDelete();
            $delete->setFrom('"'.$list->getJoinTable().'"');
            $delete->addWhere($filter);
            $delete->execute();
        }
        
        self::$disabled=false;
    }
    
    /**
     * Disables the Staged Many Many Relation Modifier
     */
    public static function disable() {
        self::$disabled=true;
    }
    
    /**
     * Enables the Staged Many Many Relation Modifier
     */
    public static function enable() {
        self::$disabled=false;
    }
    
    /**
     * Gets whether the Staged Many Many Relation Modifier is enabled or not
     * @return bool
     */
    public static function get_enabled() {
        return !self::$disabled;
    }
    
    /**
     * Return a filter expression for when getting the contents of the relationship for some foreign ID
     * @param ManyManyList $list
     * @return array
     */
    protected function foreignIDFilter(ManyManyList $list) {
        $id=$list->getForeignID();
        
        // Apply relation filter
        $key='"'.$list->getJoinTable().'"."'.$list->getForeignKey().'"';
        if(is_array($id)) {
            return array("$key IN (".DB::placeholders($id).")"=>$id);
        }else if($id!==null){
            return array($key=>$id);
        }
    }
}
?>