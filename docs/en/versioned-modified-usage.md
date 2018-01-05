Versioned Modified Extension Usage
=================
The ``VersionedModifiedExtension`` provides the basis of the modified detection of children objects that the [VersionedChildrenSupport](versioned-children-usage.md) extension relies on. It provides the following methods:

* __getIsModifiedOnStage:__ Checks to see if the version numbers between the ``Stage`` and ``Live`` state of any of the versioned children differs.
* __stagesDiffer:__ This is basically a different approach to ``getIsModifiedOnStage`` though the result from both should be the same.

When using this extension in tandem which another extension that defines ``stagesDiffer`` such as ``Versioned`` you must define a ``stagesDiffer`` method to your parent class (even for SiteTree decedents) otherwise only ``Versioned``'s ``stagesDiffer`` maybe called to do this simply add the following to your class. This method simply tells the core to look to the max value (which a true result is greater than a false result) from any of the extensions that define ``stagesDiffer``. This class does not check if it's owner differs between stages.

```php
/**
 * Compare two stages to see if they're different. Only checks the version numbers, not the actual content.
 * @param string $stage1 The first stage to check.
 * @param string $stage2
 */
public function stagesDiffer($stage1, $stage2) {
    return max($this->extend('stagesDiffer', $stage1, $stage2));
}
```


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
