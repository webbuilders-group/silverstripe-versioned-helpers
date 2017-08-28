<?php
class StagedManyManyRelationTest extends SapphireTest {
    protected static $fixture_file='StagedManyManyRelationTest.yml';
    protected $extraDataObjects=array(
                                    'StagedManyManyTestObj',
                                    'StagedManyManyTestSubObj'
                                );
    
    
    /**
     * Test updateManyManyComponents
     */
    public function testUpdateManyManyComponents() {
        $obj=$this->objFromFixture('StagedManyManyTestObj', 'item1');
        $obj->publish('Stage', 'Live');
        
        
        //Make sure we're on the draft site
        Versioned::reading_stage('Stage');
        
        
        //Make sure we're joined against the draft table
        $this->assertEquals('StagedManyManyTestObj_SubObjs', $obj->SubObjs()->getJoinTable());
        
        
        //Switch to Live
        Versioned::reading_stage('Live');
        
        
        //Make sure we're joined against the live table
        $this->assertEquals('StagedManyManyTestObj_SubObjs_Live', $obj->SubObjs()->getJoinTable());
    }
    
    /**
     * Test Publishing
     */
    public function testPublish() {
        $obj=$this->objFromFixture('StagedManyManyTestObj', 'item2');
        
        
        //Make sure the items do not exist on live
        $this->assertEquals(0, Versioned::get_by_stage('StagedManyManyTestObj', 'Live')->filter('ID', $obj->ID)->count());
        
        Versioned::reading_stage('Live');
        $this->assertEquals(0, $obj->SubObjs()->count());
        Versioned::reading_stage('Stage');
        
        
        $obj->publish('Stage', 'Live');
        
        
        Versioned::reading_stage('Live');
        
        
        //Make sure the items exist on live
        $this->assertEquals(1, Versioned::get_by_stage('StagedManyManyTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals(1, $obj->SubObjs()->count());
        
        
        Versioned::reading_stage('Stage');
    }
    
    /**
     * Test Unpublishing
     */
    public function testUnpublish() {
        $obj=$this->objFromFixture('StagedManyManyTestObj', 'item2');
        $obj->publish('Stage', 'Live');
        
        
        //Make sure the items exist on live
        Versioned::reading_stage('Live');
        $this->assertEquals(1, Versioned::get_by_stage('StagedManyManyTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals(1, $obj->SubObjs()->count());
        Versioned::reading_stage('Stage');
        
        
        $this->assertTrue($obj->doUnpublish());
        
        
        //Make sure the items do not exist on live
        Versioned::reading_stage('Live');
        $this->assertEquals(0, Versioned::get_by_stage('StagedManyManyTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals(0, $obj->SubObjs()->count());
        Versioned::reading_stage('Stage');
        
        
        //Make sure stage does still have items
        $this->assertEquals(1, $obj->SubObjs()->count());
    }
    
    /**
     * Test IsModifiedOnStage
     */
    public function testIsModifiedOnStage() {
        $obj=$this->objFromFixture('StagedManyManyTestObj', 'item3');
        $obj->publish('Stage', 'Live');
        
        
        //Make sure it's not modified on stage since we just published
        $this->assertFalse($obj->getIsModifiedOnStage());
        
        
        //Add a new item
        $newItem=new StagedManyManyTestSubObj();
        $newItem->Title='New Item';
        $newItem->write();
        $obj->SubObjs()->add($newItem);
        
        
        //Make sure it's now flagged as modified since we added an item
        $this->assertTrue($obj->getIsModifiedOnStage());
    }
    
    /**
     * Test stagesDiffer
     */
    public function testStagesDiffer() {
        $obj=$this->objFromFixture('StagedManyManyTestObj', 'item4');
        $obj->publish('Stage', 'Live');
        
        
        //Make sure it's not modified on stage since we just published
        $this->assertFalse($obj->stagesDiffer('Stage', 'Live'));
        
        
        //Add a new item
        $newItem=new StagedManyManyTestSubObj();
        $newItem->Title='New Item';
        $newItem->write();
        $obj->SubObjs()->add($newItem);
        
        
        //Make sure it's now flagged as modified since we added an item
        $this->assertTrue($obj->stagesDiffer('Stage', 'Live'));
    }
}

class StagedManyManyTestObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $many_many=array(
                                'SubObjs'=>'StagedManyManyTestSubObj',
                                'SubObjs_Live'=>'StagedManyManyTestSubObj'
                            );
    
    private static $extensions=array(
                                    'Versioned',
                                    "StagedManyManyRelationExtension('SubObjs')"
                                );
    
    /**
     * Unpublish this page - remove it from the live site
     *
     * @uses SiteTreeExtension->onBeforeUnpublish()
     * @uses SiteTreeExtension->onAfterUnpublish()
     */
    public function doUnpublish() {
        if(!$this->ID) return false;
        
        $this->invokeWithExtensions('onBeforeUnpublish', $this);
        
        $origStage=Versioned::current_stage();
        Versioned::reading_stage('Live');
        
        // This way our ID won't be unset
        $clone = clone $this;
        $clone->delete();
        
        Versioned::reading_stage($origStage);
        
        // If we're on the draft site, then we can update the status.
        // Otherwise, these lines will resurrect an inappropriate record
        if(DB::prepared_query("SELECT \"ID\" FROM \"".ClassInfo::baseDataClass($this->class)."\" WHERE \"ID\" = ?", array($this->ID))->value() && Versioned::current_stage() != 'Live') {
            $this->write();
        }
        
        $this->invokeWithExtensions('onAfterUnpublish', $this);
        
        return true;
    }
}

class StagedManyManyTestSubObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
}
?>