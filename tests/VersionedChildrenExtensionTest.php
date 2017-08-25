<?php
class VersionedChildrenExtensionTest extends SapphireTest {
    protected static $fixture_file='VersionedChildrenExtensionTest.yml';
    protected $extraDataObjects=array(
                                    'VersionedChildrenTestObj',
                                    'VersionedChildrenTestSubObj'
                                );
    
    
    /**
     * Test Publishing
     */
    public function testPublish() {
        $obj=$this->objFromFixture('VersionedChildrenTestObj', 'item1');
        
        
        //Make sure the items do not exist on live
        $this->assertEquals(0, Versioned::get_by_stage('VersionedChildrenTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals(0, Versioned::get_by_stage('VersionedChildrenTestSubObj', 'Live')->filter('ParentID', $obj->ID)->count());
        
        
        $obj->doPublish();
        
        
        //Make sure the items exist on live
        $this->assertEquals(1, Versioned::get_by_stage('VersionedChildrenTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals($obj->SubObjs()->count(), Versioned::get_by_stage('VersionedChildrenTestSubObj', 'Live')->filter('ParentID', $obj->ID)->count());
    }
    
    /**
     * Test Unpublishing
     */
    public function testUnpublish() {
        $obj=$this->objFromFixture('VersionedChildrenTestObj', 'item2');
        $obj->doPublish();
        
        
        //Make sure the items exist on live
        $this->assertEquals(1, Versioned::get_by_stage('VersionedChildrenTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals($obj->SubObjs()->count(), Versioned::get_by_stage('VersionedChildrenTestSubObj', 'Live')->filter('ParentID', $obj->ID)->count());
        
        
        $obj->doUnpublish();
        
        
        //Make sure the items do not exist on live
        $this->assertEquals(0, Versioned::get_by_stage('VersionedChildrenTestObj', 'Live')->filter('ID', $obj->ID)->count());
        $this->assertEquals(0, Versioned::get_by_stage('VersionedChildrenTestSubObj', 'Live')->filter('ParentID', $obj->ID)->count());
    }
    
    /**
     * Test Revert to Live
     */
    public function testRevertToLive() {
        $obj=$this->objFromFixture('VersionedChildrenTestObj', 'item3');
        $obj->doPublish();
        
        
        Versioned::reading_stage('Stage');
        
        
        $subObj=$obj->SubObjs()->first();
        $subObj->Title='Changed Title';
        $subObj->write();
        
        
        //Make sure the live title didn't change
        $this->assertEquals('Sub Object 3', Versioned::get_by_stage('VersionedChildrenTestSubObj', 'Live')->byID($subObj->ID)->Title);
        
        
        $obj->doRevertToLive();
        
        
        //Make sure the title rolled back
        $this->assertEquals('Sub Object 3', Versioned::get_by_stage('VersionedChildrenTestSubObj', 'Stage')->byID($subObj->ID)->Title);
    }
    
    /**
     * Test Revert to Version
     */
    public function testRevertToVersion() {
        $obj=$this->objFromFixture('VersionedChildrenTestObj', 'item4');
        
        
        //Because tests run quickly we need to back the LastEdited field up a few minutes otherwise we won't be able to accurately rollback
        DB::prepared_query('UPDATE "VersionedChildrenTestObj" SET "LastEdited"=DATE_SUB("LastEdited", INTERVAL 10 MINUTE) WHERE "ID"= ?', array($obj->ID));
        DB::prepared_query('UPDATE "VersionedChildrenTestObj_versions" SET "LastEdited"=DATE_SUB("LastEdited", INTERVAL 10 MINUTE) WHERE "RecordID"= ?', array($obj->ID));
        DB::prepared_query('UPDATE "VersionedChildrenTestSubObj" SET "LastEdited"=DATE_SUB("LastEdited", INTERVAL 10 MINUTE) WHERE "ParentID"= ?', array($obj->ID));
        DB::prepared_query('UPDATE "VersionedChildrenTestSubObj_versions" SET "LastEdited"=DATE_SUB("LastEdited", INTERVAL 10 MINUTE) WHERE "ParentID"= ?', array($obj->ID));
        
        
        //Get the current version number
        $startingVersion=$obj->Version;
        $this->assertGreaterThan(0, $startingVersion);
        
        
        Versioned::reading_stage('Stage');
        
        
        $obj->Title='Test Object 4 (changed)';
        $obj->write();
        
        
        $subObj=$obj->SubObjs()->first();
        
        $subObjVersion=$subObj->Version;
        
        $subObj->Title='Sub Object 4 (changed)';
        $subObj->write();
        
        
        //Make sure a new version object was created
        $this->assertGreaterThan($startingVersion, DB::prepared_query('SELECT MAX("Version") FROM "VersionedChildrenTestObj_versions" WHERE "RecordID"= ?', array($obj->ID))->Value(), 'No new version record was created for the panel page when it should have been because the panel views changed');
        
        
        //Make sure a new version for the sub object was created
        $this->assertGreaterThan($subObjVersion, DB::prepared_query('SELECT MAX("Version") FROM "VersionedChildrenTestSubObj_versions" WHERE "RecordID"= ?', array($subObj->ID))->Value(), 'No new version record was created for the first panel');
        
        
        $obj->doRollbackTo(1);
        
        
        //Refetch both objects and make sure the title reverted
        $obj=VersionedChildrenTestObj::get()->byID($obj->ID);
        $subObj=VersionedChildrenTestSubObj::get()->byID($subObj->ID);
        
        
        $this->assertEquals('Test Object 4', $obj->Title);
        $this->assertEquals('Sub Object 4', $subObj->Title);
    }
}

class VersionedChildrenTestObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $has_many=array(
                                'SubObjs'=>'VersionedChildrenTestSubObj'
                            );
    
    private static $extensions=array(
                                    'Versioned',
                                    "VersionedChildrenExtension('SubObjs')"
                                );
    
    
    public function doPublish() {
        $original=Versioned::get_one_by_stage("SiteTree", "Live", array('"SiteTree"."ID"'=>$this->ID));
        if(!$original) {
            $original=new SiteTree();
        }
        
        $this->invokeWithExtensions('onBeforePublish', $original);
        
        $this->publish('Stage', 'Live');
        
        $this->invokeWithExtensions('onAfterPublish', $original);
    }
    
    public function doUnpublish() {
        $this->invokeWithExtensions('onBeforeUnpublish', $this);
        
        $origStage=Versioned::current_stage();
        Versioned::reading_stage('Live');
        
        // This way our ID won't be unset
        $clone=clone $this;
        $clone->delete();
        
        Versioned::reading_stage($origStage);
        
        $this->invokeWithExtensions('onAfterUnpublish', $this);
    }
    
    public function doRevertToLive() {
        $this->invokeWithExtensions('onBeforeRevertToLive', $this);
        
        $this->publish('Live', 'Stage', false);
        
        // Use a clone to get the updates made by $this->publish
        $clone=VersionedChildrenTestObj::get()->byID($this->ID);
        $clone->writeWithoutVersion();
        
        $this->invokeWithExtensions('onAfterRevertToLive', $this);
        return true;
    }
}

class VersionedChildrenTestSubObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $has_one=array(
                            'Parent'=>'VersionedChildrenTestObj'
                        );
    
    private static $extensions=array(
                                    'Versioned'
                                );
}
?>