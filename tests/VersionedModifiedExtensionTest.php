<?php
namespace WebbuildersGroup\VersionedHelpers\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;
use WebbuildersGroup\VersionedHelpers\Tests\TestObjects\VersionedModifiedTestObj;
use WebbuildersGroup\VersionedHelpers\Tests\TestObjects\VersionedModifiedTestSubObj;

class VersionedModifiedExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'VersionedModifiedExtensionTest.yml';
    protected static $extra_dataobjects = [
        VersionedModifiedTestObj::class,
        VersionedModifiedTestSubObj::class,
    ];
    
    /**
     * Test stagesDiffer between Stage and Live just after publishing when it should not be modified on stage then
     * after changing the staged version without publishing making sure children count
     */
    public function testStagesDiffer()
    {
        $obj = $this->objFromFixture(VersionedModifiedTestObj::class, 'item2');
        
        
        //Make sure stagesDiffer(Versioned::DRAFT, Versioned::LIVE) returns true when not published
        $this->assertTrue($obj->stagesDiffer());
        
        
        //Publish the object and it's children
        $obj->publishRecursive();
        
        
        //Make sure stagesDiffer() returns false
        $this->assertFalse($obj->stagesDiffer());
        
        
        //Force versioned back to stage just in case
        Versioned::set_stage(Versioned::DRAFT);
        
        
        //Change the draft
        $obj->Title = $obj->Title . ' (changed)';
        $obj->write();
        
        
        //Make sure stagesDiffer() returns true
        $this->assertTrue($obj->stagesDiffer());
        
        
        //Publish the object and it's children
        $obj->publishRecursive();
        foreach ($obj->SubObjs() as $subObj) {
            $subObj->publish(Versioned::DRAFT, Versioned::LIVE);
        }
        
        
        //Make sure stagesDiffer() returns false
        $this->assertFalse($obj->stagesDiffer());
        
        
        //Force versioned back to stage just in case
        Versioned::set_stage(Versioned::DRAFT);
        
        
        //Change a sub object on draft
        $subObj = $obj->SubObjs()->first();
        $subObj->Title = $subObj->Title . ' (changed)';
        $subObj->write();
        
        
        //Make sure stagesDiffer(Versioned::DRAFT, Versioned::LIVE) returns true
        $this->assertTrue($obj->stagesDiffer());
    }
}
