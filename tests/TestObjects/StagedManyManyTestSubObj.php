<?php
namespace WebbuildersGroup\VersionedHelpers\Tests\TestObjects;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;


class StagedManyManyTestSubObj extends DataObject implements TestOnly {
    private static $db=array(
                            'Title'=>'Varchar(50)'
                        );
    
    private static $table_name='StagedManyManyTestSubObj';
}
?>