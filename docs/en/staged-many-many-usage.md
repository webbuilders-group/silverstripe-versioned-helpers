Staged Many_Many Relation Extension Usage
=================
The ``StagedManyManyRelationExtension`` extension allows you to have a draft a live state for a ``many_many`` relationship. It's pretty simple to implement, like most of the other extensions in this module you must pass the relationships into the extension. However you must also ensure that you add a copy of the relationship with the suffix ``_Live``. You do not need any of the other extensions in this module to use this extension however it will work with them. For example:

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
     * Removes all of the Images from the correct stage before deleting
     */
    protected function onBeforeDelete() {
        parent::onBeforeDelete();

        $this->Images()->removeAll();
    }
```
