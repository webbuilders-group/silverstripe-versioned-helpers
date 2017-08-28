Staged Many_Many Relation Extension Usage
=================
The ``StagedManyManyRelationExtension`` extension allows you to have a draft a live state for a ``many_many`` relationship. It's pretty simple to implement, like most of the other extensions in this module you must pass the relationships into the extension. However you must also ensure that you add a copy of the relationship with the suffix ``_Live``. You do not need any of the other extensions in this module to use this extension however it will work with them. In both your PHP code and your template code you simply reference the draft relationship (in the example below this would be ``Images``) and not the live relationship (in the example below this would be ``Images_Live``).


Implementation Example:
```php
<?php
class MyDataObject extends DataObject {
    private static $many_many=array(
                                    'Images'=>'Image',
                                    'Images_Live'=>'Image'
                                );

    private static $extensions=array(
                                    "StagedManyManyRelationExtension('Images')"
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
```


## Non-SiteTree Version Objects
Note that ``SiteTree`` defines ``doPublish``, ``doUnpublish``, ``doRevertToLive`` and ``doRollback`` methods these methods call corresponding "onAfter" extension points. If you have your own versioned ``DataObject`` descendent you must ensure that you have methods that call the following extension points for the appropriate actions. If you do not then the ``StagedManyManyRelationExtension`` will not work as expected since it is not called.

* __onAfterUnpublish:__ This should be called after the object is unpublished.
