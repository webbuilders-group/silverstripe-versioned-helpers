<?php
namespace WebbuildersGroup\VersionedHelpers\Extensions;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;


/**
 * Class \WebbuildersGroup\VersionedHelpers\Extensions\VersionedModifiedExtension
 *
 */
class VersionedModifiedExtension extends DataExtension {
    private static $casting=array(
                                'isModifiedOnDraft'=>'Boolean',
                                'isPublished'=>'Boolean'
                            );
    
    /**
     * Compare two stages to see if they're different. Only checks the version numbers, not the actual content.
     * @return bool
     */
    public function stagesDiffer() {
        $stagesAreEqual=true;
        
        $ownerClass=$this->owner->ClassName;
        $owns=$this->owner->config()->get('owns');
        
        //Check to see if the items differ
        foreach($owns as $relation) {
            $relClass=$this->owner->getSchema()->hasManyComponent($ownerClass, $relation);
            if(!$relClass) {
                continue;
            }
            
            $baseClass=DataObject::getSchema()->baseDataClass($relClass);
            
            $parentField=$this->owner->getSchema()->getRemoteJoinField($ownerClass, $relation, 'has_many');
            if(!$parentField) {
                continue;
            }
            
            //Make sure the Items all exist on both stages
            $stageItems=Versioned::get_by_stage($relClass, Versioned::DRAFT)->filter($parentField, $this->owner->ID)->column('ID');
            $liveItems=Versioned::get_by_stage($relClass, Versioned::LIVE)->filter($parentField, $this->owner->ID)->column('ID');
            
            $diff=array_merge(array_diff($stageItems, $liveItems), array_diff($liveItems, $stageItems));
            if(count($diff)>0) {
                $stagesAreEqual=false;
            }
            
            //Check each item to see if it is modified
            if($stagesAreEqual) {
                $hasDifferMethod=singleton($relClass)->hasMethod('stagesDiffer');
                    
                $table1=DataObject::getSchema()->tableName($relClass);
                $table2=DataObject::getSchema()->tableName($relClass).'_'.Versioned::LIVE;
                foreach($stageItems as $itemID) {
                    if($hasDifferMethod) {
                        $item=Versioned::get_one_by_stage($baseClass, Versioned::DRAFT, '"ID"='.intval($itemID));
                        if(!empty($item) && $item!==false && $item->exists()) {
                            $stagesAreEqual=!$item->stagesDiffer();
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
}
?>