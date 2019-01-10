<?php
namespace WebbuildersGroup\VersionedHelpers\Tests\TestObjects;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;


class VersionedModifiedTestSubObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $has_one=array(
                            'Parent'=>VersionedModifiedTestObj::class
                        );
    
    private static $extensions=array(
                                    Versioned::class
                                );
    
    private static $table_name='VersionedModifiedTestSubObj';
}
