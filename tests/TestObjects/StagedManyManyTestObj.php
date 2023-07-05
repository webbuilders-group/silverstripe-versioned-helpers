<?php
namespace WebbuildersGroup\VersionedHelpers\Tests\TestObjects;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use WebbuildersGroup\VersionedHelpers\Extensions\StagedManyManyRelationExtension;

class StagedManyManyTestObj extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(50)',
    ];

    private static $many_many = [
        'SubObjs' => StagedManyManyTestSubObj::class,
        'SubObjs_Live' => StagedManyManyTestSubObj::class,
    ];

    private static $extensions = [
        Versioned::class,
        StagedManyManyRelationExtension::class
    ];

    private static $staged_many_many = [
        'SubObjs'
    ];

    private static $table_name = 'StagedManyManyTestObj';
}
