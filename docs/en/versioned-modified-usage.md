Versioned Modified Extension Usage
=================
The ``VersionedModifiedExtension`` provides the basis of the modified detection of children objects that the [VersionedChildrenSupport](versioned-children-usage.md) extension relies on. It provides the following methods:

* __getIsModifiedOnStage:__ Checks to see if the version numbers between the ``Stage`` and ``Live`` state of any of the versioned children differs.
* __stagesDiffer:__ This is basically a different approach to ``getIsModifiedOnStage`` though the result from both should be the same.


__Note:__ When using this extension on a DataObject that is not a descendent of SiteTree and also has the versioned extension you should also apply the ``VersionedSupportExtension`` and declare a ``getIsModifiedOnStage`` like the following.

```php
/**
 * Invokes getIsModifiedOnStage on extensions
 * @return bool
 */
public function getIsModifiedOnStage() {
    $isModified=false;

    $this->extend('getIsModifiedOnStage', $isModified);

    return $isModified;
}
```
