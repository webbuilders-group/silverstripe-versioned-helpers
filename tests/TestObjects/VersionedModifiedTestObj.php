<?php
namespace WebbuildersGroup\VersionedHelpers\Tests\TestObjects;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use WebbuildersGroup\VersionedHelpers\Extensions\VersionedModifiedExtension;

class VersionedModifiedTestObj extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(50)',
    ];

    private static $has_many = [
        'SubObjs' => VersionedModifiedTestSubObj::class,
    ];

    private static $extensions = [
        Versioned::class,
        VersionedModifiedExtension::class
    ];

    private static $owns = [
        'SubObjs'
    ];

    private static $table_name = 'VersionedModifiedTestObj';

    /**
     * Compare two stages to see if they're different. Only checks the version numbers, not the actual content.
     */
    public function stagesDiffer()
    {
        return max($this->extend('stagesDiffer'));
    }

    /**
     * Compares current draft with live version, and returns true if these versions differ, meaning there have been unpublished changes to the draft site.
     * @return bool
     */
    public function isModifiedOnDraft()
    {
        return $this->isOnDraft() && $this->stagesDiffer();
    }
}
