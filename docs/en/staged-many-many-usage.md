Staged Many_Many Relation Extension Usage
=================
The ``WebbuildersGroup\VersionedHelpers\Extensions\StagedManyManyRelationExtension`` extension allows you to have a draft a live state for a ``many_many`` relationship. It's pretty simple to implement, like most of the other extensions in this module you must pass the relationships into the extension. However you must also ensure that you add a copy of the relationship with the suffix ``_Live``. You do not need any of the other extensions in this module to use this extension however it will work with them. In both your PHP code and your template code you simply reference the draft relationship (in the example below this would be ``Images``) and not the live relationship (in the example below this would be ``Images_Live``).


Implementation Example:
```php
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use WebbuildersGroup\VersionedHelpers\Extensions\StagedManyManyRelationExtension;

class MyDataObject extends DataObject
{
    private static $many_many = [
        'Images' => Image::class,
        'Images_Live' =>Image::class,
    ];

    private static $extensions = [
        Versioned::class,
        StagedManyManyRelationExtension::class,
    ];

    private static $staged_many_many = [
        'Images',
    ];

    /**
     * Compares current draft with live version, and returns true if these versions differ, meaning there have been unpublished changes to the draft site.
     * @return bool
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
        return max($this->extend('isModifiedOnDraft'));
    }
}
```


## Duplicating
If you are duplicating DataObjects with the ``WebbuildersGroup\VersionedHelpers\Extensions\StagedManyManyRelationExtension`` on them you will want to not duplicate the ``_Live`` relationships. To do this unfortunately we need to replace the ``DataObject::duplicateManyManyRelations()`` method with the following since there is no other way to hook into this method.
```php
/**
 * Duplicates a single many_many relation from one object to another.
 * @param DataObject $sourceObject
 * @param DataObject $destinationObject
 * @param string $relation
 */
protected function duplicateManyManyRelation($sourceObject, $destinationObject, $relation)
{
    //If the relationship ends with "_Live" assume it's a published version of a relationship and skip if present in the config
    if (preg_match('/^(.*?)_Live$/', $relation)==true && is_array($this->config()->staged_many_many) && in_array(preg_replace('/^(.*?)_Live$/', '$1', $relation), $this->config()->staged_many_many)) {
        return;
    }

    return parent::duplicateManyManyRelation($sourceObject, $destinationObject, $relation);
}
```
