Staged Many_Many Relation Extension Usage
=================
The ``StagedManyManyRelationExtension`` extension allows you to have a draft a live state for a ``many_many`` relationship. It's pretty simple to implement, like most of the other extensions in this module you must pass the relationships into the extension. However you must also ensure that you add a copy of the relationship with the suffix ``_Live``. You do not need any of the other extensions in this module to use this extension however it will work with them. In both your PHP code and your template code you simply reference the draft relationship (in the example below this would be ``Images``) and not the live relationship (in the example below this would be ``Images_Live``).


Implementation Example:
```php
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


## Duplicating
If you are duplicating DataObjects with the ``StagedManyManyRelationExtension`` on them you will want to not duplicate the ``_Live`` relationships. To do this unfortunately we need to replace the ``DataObject::duplicateManyManyRelations()`` method with the following since there is no other way to hook into this method.
```php
/**
 * Copies the many_many and belongs_many_many relations from one object to another instance of the name of object
 * The destination object must be written to the database already and have an ID. Writing is performed
 * automatically when adding the new relations.
 *
 * @param DataObject $source Object the source object to duplicate from
 * @param DataObject $destination Object the destination object to populate with the duplicated relations
 * @return DataObject with the new many_many relations copied in
 */
protected function duplicateManyManyRelations($sourceObject, $destinationObject) {
    if(!$destinationObject || $destinationObject->ID<1) {
        user_error("Can't duplicate relations for an object that has not been written to the database", E_USER_ERROR);
    }

    //If the StagedManyManyRelationExtension is not attached let DataObject handle this process
    if(!$this->hasExtension('StagedManyManyRelationExtension')) {
        return parent::duplicateManyManyRelations($sourceObject, $destinationObject);
    }

    //duplicate complex relations
    // DO NOT copy has_many relations, because copying the relation would result in us changing the has_one
    // relation on the other side of this relation to point at the copy and no longer the original (being a
    // has_one, it can only point at one thing at a time). So, all relations except has_many can and are copied
    if($sourceObject->hasOne()) {
        foreach($sourceObject->hasOne() as $name=>$type) {
            $this->duplicateRelations($sourceObject, $destinationObject, $name);
        }
    }

    if($sourceObject->manyMany()) {
        foreach($sourceObject->manyMany() as $name=>$type) {
            //If the relationship ends with "_Live" assume it's a published version of a relationship and skip
            if(preg_match('/^(.*?)_Live$/', $name)==true) {
                continue;
            }

            //many_many include belongs_many_many
            $this->duplicateRelations($sourceObject, $destinationObject, $name);
        }
    }

    return $destinationObject;
}

/**
 * Helper function to duplicate relations from one object to another
 * @param $sourceObject the source object to duplicate from
 * @param $destinationObject the destination object to populate with the duplicated relations
 * @param $name the name of the relation to duplicate (e.g. members)
 */
private function duplicateRelations($sourceObject, $destinationObject, $name) {
    $relations=$sourceObject->$name();
    if($relations) {
        if($relations instanceOf RelationList) {   //many-to-something relation
            if($relations->Count()>0) {  //with more than one thing it is related to
                foreach($relations as $relation) {
                    $destinationObject->$name()->add($relation);
                }
            }
        }else {//one-to-one relation
            $destinationObject->{"{$name}ID"}=$relations->ID;
        }
    }
}
```
