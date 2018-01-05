<?php
class VersionedModifiedExtensionTest extends SapphireTest {
    protected static $fixture_file='VersionedModifiedExtensionTest.yml';
    protected $extraDataObjects=array(
                                    'VersionedModifiedTestObj',
                                    'VersionedModifiedTestSubObj'
                                );
    
    
    /**
     * Test IsModifiedOnStage just after publishing when it should not be modified on stage then after changing
     * the staged version without publishing making sure children count
     */
    public function testIsModifiedOnStage() {
        $obj=$this->objFromFixture('VersionedModifiedTestObj', 'item1');
        
        
        //Make sure getIsModifiedOnStage() returns true when not published
        $this->assertTrue($obj->getIsModifiedOnStage());
        
        
        //Publish the object and it's children
        $obj->publish('Stage', 'Live');
        foreach($obj->SubObjs() as $subObj) {
            $subObj->publish('Stage', 'Live');
        }
        
        
        //Make sure getIsModifiedOnStage() returns false
        $this->assertFalse($obj->getIsModifiedOnStage());
        
        
        //Force versioned back to stage just in case
        Versioned::reading_stage('Stage');
        
        
        //Change the draft
        $obj->Title=$obj->Title.' (changed)';
        $obj->write();
        
        
        //Make sure getIsModifiedOnStage() returns true
        $this->assertTrue($obj->getIsModifiedOnStage());
        
        
        //Publish the object and it's children
        $obj->publish('Stage', 'Live');
        foreach($obj->SubObjs() as $subObj) {
            $subObj->publish('Stage', 'Live');
        }
        
        
        //Make sure getIsModifiedOnStage() returns false
        $this->assertFalse($obj->getIsModifiedOnStage());
        
        
        //Force versioned back to stage just in case
        Versioned::reading_stage('Stage');
        
        
        //Change a sub object on draft
        $subObj=$obj->SubObjs()->first();
        $subObj->Title=$subObj->Title.' (changed)';
        $subObj->write();
        
        
        //Make sure getIsModifiedOnStage() returns true
        $this->assertTrue($obj->getIsModifiedOnStage());
    }
    
    /**
     * Test stagesDiffer between Stage and Live just after publishing when it should not be modified on stage then
     * after changing the staged version without publishing making sure children count
     */
    public function testStagesDiffer() {
        $obj=$this->objFromFixture('VersionedModifiedTestObj', 'item2');
        
        
        //Make sure stagesDiffer('Stage', 'Live') returns true when not published
        $this->assertTrue($obj->stagesDiffer('Stage', 'Live'));
        
        
        //Publish the object and it's children
        $obj->publish('Stage', 'Live');
        foreach($obj->SubObjs() as $subObj) {
            $subObj->publish('Stage', 'Live');
        }
        
        
        //Make sure stagesDiffer('Stage', 'Live') returns false
        $this->assertFalse($obj->stagesDiffer('Stage', 'Live'));
        
        
        //Force versioned back to stage just in case
        Versioned::reading_stage('Stage');
        
        
        //Change the draft
        $obj->Title=$obj->Title.' (changed)';
        $obj->write();
        
        
        //Make sure stagesDiffer('Stage', 'Live') returns true
        $this->assertTrue($obj->stagesDiffer('Stage', 'Live'));
        
        
        //Publish the object and it's children
        $obj->publish('Stage', 'Live');
        foreach($obj->SubObjs() as $subObj) {
            $subObj->publish('Stage', 'Live');
        }
        
        
        //Make sure stagesDiffer('Stage', 'Live') returns false
        $this->assertFalse($obj->stagesDiffer('Stage', 'Live'));
        
        
        //Force versioned back to stage just in case
        Versioned::reading_stage('Stage');
        
        
        //Change a sub object on draft
        $subObj=$obj->SubObjs()->first();
        $subObj->Title=$subObj->Title.' (changed)';
        $subObj->write();
        
        
        //Make sure stagesDiffer('Stage', 'Live') returns true
        $this->assertTrue($obj->stagesDiffer('Stage', 'Live'));
    }
}

class VersionedModifiedTestObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $has_many=array(
                                'SubObjs'=>'VersionedModifiedTestSubObj'
                            );
    
    private static $extensions=array(
                                    'Versioned',
                                    "VersionedModifiedExtension('SubObjs')"
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
        
        $this->extend('getIsModifiedOnStage', $isModified);
        
        return $isModified;
    }
    
    /**
     * Compare two stages to see if they're different. Only checks the version numbers, not the actual content.
     * @param string $stage1 The first stage to check.
     * @param string $stage2
     */
    public function stagesDiffer($stage1, $stage2) {
        return max($this->extend('stagesDiffer', $stage1, $stage2));
    }
}

class VersionedModifiedTestSubObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $has_one=array(
                            'Parent'=>'VersionedModifiedTestObj'
                        );
    
    private static $extensions=array(
                                    'Versioned'
                                );
}
?>