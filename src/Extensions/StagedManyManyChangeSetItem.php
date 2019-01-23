<?php
namespace WebbuildersGroup\VersionedHelpers\Extensions;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
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
        
        
        $schema=$object->getSchema();
        foreach($relations as $relation) {
            $relationLive=$relation.'_Live';
            
            $relClass=$schema->manyManyComponent($ownerClass, $relation);
            if(!$relClass) {
                user_error('Could not find the many_many relationship "'.$relation.'" on "'.$ownerClass.'"', E_USER_ERROR);
            }
            
            $relLiveClass=$schema->manyManyComponent($ownerClass, $relationLive);
            if(!$relLiveClass || $relClass['childClass']!=$relLiveClass['childClass']) {
                user_error('Could not find the many_many relationship "'.$relationLive.'" on "'.$ownerClass.'" or the class does not match.', E_USER_ERROR);
            }
            
            
            //Make sure the Items all exist on both stages
            $stageItems=iterator_to_array(DB::prepared_query('SELECT * FROM "'.Convert::raw2sql($relClass['join']).'" WHERE "'.Convert::raw2sql($relClass['parentField']).'"= ?', array($object->ID)));
            $liveItems=iterator_to_array(DB::prepared_query('SELECT * FROM "'.Convert::raw2sql($relLiveClass['join']).'" WHERE "'.Convert::raw2sql($relLiveClass['parentField']).'"= ?', array($object->ID)));
            $stageValues=array_column($stageItems, $relClass['childField']);
            $liveValues=array_column($liveItems, $relLiveClass['childField']);
            
            $diff=array_merge(array_diff($stageValues, $liveValues), array_diff($liveValues, $stageValues));
            if(count($diff)>0) {
                $stagesAreEqual=false;
            }
            
            
            //Differ the extra fields if there are any
            if($stagesAreEqual) {
                $stageExtra=$schema->manyManyExtraFieldsForComponent($ownerClass, $relation);
                $liveExtra=$schema->manyManyExtraFieldsForComponent($ownerClass, $relationLive);
                
                if(!empty($stageExtra) && !empty($liveExtra) && count(array_merge(array_diff_key($stageExtra, $liveExtra), array_diff_key($liveExtra, $stageExtra)))==0) {
                    foreach($stageExtra as $extraField=>$fieldType) {
                        $stageValues=array_column($stageItems, $extraField, $relClass['childField']);
                        $liveValues=array_column($liveItems, $extraField, $relLiveClass['childField']);
                        
                        $diff=array_merge(array_diff_assoc($stageValues, $liveValues), array_diff_assoc($liveValues, $stageValues));
                        if(count($diff)>0) {
                            $stagesAreEqual=false;
                        }
                        
                        if(!$stagesAreEqual) {
                            break;
                        }
                    }
                }
            }
        }
        
        if($wasEnabled) {
            StagedManyManyRelationExtension::enable();
        }
        
        return !$stagesAreEqual;
    }
}