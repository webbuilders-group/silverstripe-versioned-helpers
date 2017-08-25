<?php
class VersionedSupportExtensionTest extends SapphireTest {
    protected static $fixture_file='VersionedSupportExtensionTest.yml';
    protected $extraDataObjects=array(
                                    'VersionedSupportTestObj'
                                );
    
    /**
     * Test ExistsOnLive both for a stage only and a published object
     */
    public function testExistsOnLive() {
        $obj=$this->objFromFixture('VersionedSupportTestObj', 'item1');
        
        //Verify the object does not exist on live
        $this->assertEquals(0, Versioned::get_by_stage('VersionedSupportTestObj', 'Live')->filter('ID', $obj->ID)->count());
        
        
        //Check to see if getExistsOnLive() returns false
        $this->assertFalse($obj->getExistsOnLive());
        
        
        //Publish the object and see if it exists on live
        $obj->publish('Stage', 'Live');
        $this->assertEquals(1, Versioned::get_by_stage('VersionedSupportTestObj', 'Live')->filter('ID', $obj->ID)->count());
        
        //Check to see if getExistsOnLive() returns true
        $this->assertTrue($obj->getExistsOnLive());
        
    }
    
    /**
     * Test IsModifiedOnStage just after publishing when it should not be modified on stage then after changing the staged version without publishing
     */
    public function testIsModifiedOnStage() {
        $obj=$this->objFromFixture('VersionedSupportTestObj', 'item2');
        
        
        //Make sure getIsModifiedOnStage() returns true when not published
        $this->assertTrue($obj->getIsModifiedOnStage());
        
        
        //Publish the object
        $obj->publish('Stage', 'Live');
        
        
        //Make sure getIsModifiedOnStage() returns false
        $this->assertFalse($obj->getIsModifiedOnStage());
        
        
        //Force versioned back to stage just in case
        Versioned::reading_stage('Stage');
        
        
        //Change the draft
        $obj->Title=$obj->Title.' (changed)';
        $obj->write();
        
        
        //Make sure getIsModifiedOnStage() returns true
        $this->assertTrue($obj->getIsModifiedOnStage());
    }
    
    /**
     * Test IsDeletedFromStage before publishing, after, then after deleting the draft where it should return true
     */
    public function testIsDeletedFromStage() {
        $obj=$this->objFromFixture('VersionedSupportTestObj', 'item2');
        
        
        //Make sure getIsDeletedFromStage() returns false
        $this->assertFalse($obj->getIsDeletedFromStage());
        
        
        //Publish the object
        $obj->publish('Stage', 'Live');
        
        
        //Make sure getIsDeletedFromStage() still returns false
        $this->assertFalse($obj->getIsDeletedFromStage());
        
        
        //Force versioned back to stage just in case
        Versioned::reading_stage('Stage');
        
        
        //Delete the object from the draft site
        $obj->delete();
        
        
        //Make sure getIsDeletedFromStage() returns true
        $this->assertTrue($obj->getIsDeletedFromStage());
    }
}

class VersionedSupportTestObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $extensions=array(
                                    'Versioned',
                                    'VersionedSupportExtension'
                                );
}
?>