<?php
namespace WebbuildersGroup\VersionedHelpers\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;
use WebbuildersGroup\VersionedHelpers\Tests\TestObjects\StagedManyManyTestObj;
use WebbuildersGroup\VersionedHelpers\Tests\TestObjects\StagedManyManyTestSubObj;


class StagedManyManyRelationTest extends SapphireTest {
    protected static $fixture_file='StagedManyManyRelationTest.yml';
    protected static $extra_dataobjects=array(
                                            StagedManyManyTestObj::class,
                                            StagedManyManyTestSubObj::class
                                        );
    
    
    /**
     * Test updateManyManyComponents
     */
    public function testUpdateManyManyComponents() {
        $obj=$this->objFromFixture(StagedManyManyTestObj::class, 'item1');
        $obj->publishRecursive();
        
        
        //Make sure we're on the draft site
        Versioned::set_stage(Versioned::DRAFT);
        
        
        //Make sure we're joined against the draft table
        $this->assertEquals('StagedManyManyTestObj_SubObjs', $obj->SubObjs()->getJoinTable());
        
        
        //Switch to Live
        Versioned::set_stage(Versioned::LIVE);
        
        
        //Make sure we're joined against the live table
        $this->assertEquals('StagedManyManyTestObj_SubObjs_Live', $obj->SubObjs()->getJoinTable());
    }
    
    /**
     * Test Publishing
     */
    public function testPublish() {
        $obj=$this->objFromFixture(StagedManyManyTestObj::class, 'item2');
        
        
        //Make sure the items do not exist on live
        $this->assertEquals(0, Versioned::get_by_stage(StagedManyManyTestObj::class, Versioned::LIVE)->filter('ID', $obj->ID)->count());
        
        Versioned::set_stage(Versioned::LIVE);
        $this->assertEquals(0, $obj->SubObjs()->count());
        Versioned::set_stage(Versioned::DRAFT);
        
        
        $obj->publishRecursive();
        
        
        Versioned::set_stage(Versioned::LIVE);
        
        
        //Make sure the items exist on live
        $this->assertEquals(1, Versioned::get_by_stage(StagedManyManyTestObj::class, Versioned::LIVE)->filter('ID', $obj->ID)->count());
        $this->assertEquals(1, $obj->SubObjs()->count());
        
        
        Versioned::set_stage(Versioned::DRAFT);
    }
    
    /**
     * Test Unpublishing
     */
    public function testUnpublish() {
        $obj=$this->objFromFixture(StagedManyManyTestObj::class, 'item2');
        $obj->publishRecursive();
        
        
        //Make sure the items exist on live
        Versioned::set_stage(Versioned::LIVE);
        $this->assertEquals(1, Versioned::get_by_stage(StagedManyManyTestObj::class, Versioned::LIVE)->filter('ID', $obj->ID)->count());
        $this->assertEquals(1, $obj->SubObjs()->count());
        Versioned::set_stage(Versioned::DRAFT);
        
        
        $this->assertTrue($obj->doUnpublish());
        
        
        //Make sure the items do not exist on live
        Versioned::set_stage(Versioned::LIVE);
        $this->assertEquals(0, Versioned::get_by_stage(StagedManyManyTestObj::class, Versioned::LIVE)->filter('ID', $obj->ID)->count());
        $this->assertEquals(0, $obj->SubObjs()->count());
        Versioned::set_stage(Versioned::DRAFT);
        
        
        //Make sure stage does still have items
        $this->assertEquals(1, $obj->SubObjs()->count());
    }
    
    /**
     * Test stagesDiffer
     */
    public function testStagesDiffer() {
        $obj=$this->objFromFixture(StagedManyManyTestObj::class, 'item4');
        $obj->publishRecursive();
        
        
        //Make sure it's not modified on stage since we just published
        $this->assertFalse($obj->stagesDiffer());
        
        
        //Add a new item
        $newItem=new StagedManyManyTestSubObj();
        $newItem->Title='New Item';
        $newItem->write();
        $obj->SubObjs()->add($newItem);
        
        
        //Make sure it's now flagged as modified since we added an item
        $this->assertTrue($obj->stagesDiffer());
    }
}
?>