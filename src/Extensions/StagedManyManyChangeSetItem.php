<?php
namespace WebbuildersGroup\VersionedHelpers\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\ChangeSetItem;


/**
 * Class \WebbuildersGroup\VersionedHelpers\Extensions\StagedManyManyChangeSetItem
 *
 */
class StagedManyManyChangeSetItem extends DataExtension {
    public function updateChangeType(&$type, $draftVersion, $liveVersion) {
        //Make sure we have a change type of none if not do nothing
        if($type!=ChangeSetItem::CHANGE_NONE) {
            return;
        }
        
        
        //Make sure the owner has the StagedManyManyRelationExtension and has items before we proceed
        $object=$this->owner->Object();
        if(!empty($object) && $object!==false && $object->exists() && $object->hasExtension(StagedManyManyRelationExtension::class)) {
            if(($relations=$object->config()->staged_many_many) && $this->objectStagesDiffer($object, $relations)) {
                $type=ChangeSetItem::CHANGE_MODIFIED;
            }
        }
    }
    
    /**
     * Compare two stages to see if they're different. Only checks the ID's match, not the actual content.
     * @param DataObject $object Owner Object to check
     * @param array $relations Array of relations to check against
     * @return bool Returns boolean true if the stages are equal
     */
    protected function objectStagesDiffer(DataObject $object, $relations=null) {
        $wasEnabled=StagedManyManyRelationExtension::get_enabled();
        StagedManyManyRelationExtension::disable();
        
        $stagesAreEqual=true;
        
        $ownerClass=$object->ClassName;
        
        if(!is_array($relations) || count($relations)==0) {
            return false;
        }
        
        if(!is_numeric($this->owner->ID)) {
            if($wasEnabled) {
                StagedManyManyRelationExtension::enable();
            }
            
            return false;
        }
        
        foreach($relations as $relation) {
            $relationLive=$relation.'_Live';
            
            $relClass=$object->getSchema()->manyManyComponent($ownerClass, $relation);
            if(!$relClass) {
                user_error('Could not find the many_many relationship "'.$relation.'" on "'.$ownerClass.'"', E_USER_ERROR);
            }
            
            $relLiveClass=$object->getSchema()->manyManyComponent($ownerClass, $relationLive);
            if(!$relLiveClass || $relClass['childClass']!=$relLiveClass['childClass']) {
                user_error('Could not find the many_many relationship "'.$relationLive.'" on "'.$ownerClass.'" or the class does not match.', E_USER_ERROR);
            }
            
            
            //Make sure the Items all exist on both stages
            $stageItems=$object->$relation()->column('ID');
            $liveItems=$object->$relationLive()->column('ID');
            
            $diff=array_merge(array_diff($stageItems, $liveItems), array_diff($liveItems, $stageItems));
            if(count($diff)>0) {
                $stagesAreEqual=false;
            }
            
            if(!$stagesAreEqual) {
                break;
            }
        }
        
        if($wasEnabled) {
            StagedManyManyRelationExtension::enable();
        }
        
        return !$stagesAreEqual;
    }
}
