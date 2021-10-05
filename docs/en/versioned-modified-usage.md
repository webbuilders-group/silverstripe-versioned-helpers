Versioned Modified Extension Usage
=================
The ``WebbuildersGroup\VersionedHelpers\Extensions\VersionedModifiedExtension`` provides the basis of the modified detection of children objects. It provides the following methods:

* __stagesDiffer:__ Checks to see if the version numbers between the ``Stage`` and ``Live`` state of any of the versioned children differs.
* __isModifiedOnDraft:__ Checks to see if the version numbers between the ``Stage`` and ``Live`` state of any of the versioned children differs.

When using this extension in tandem which another extension that defines ``stagesDiffer`` such as ``Versioned`` you must define a ``stagesDiffer`` method to your parent class (even for SiteTree decedents) otherwise only ``Versioned``'s ``stagesDiffer`` maybe called to do this simply add the following to your class. This method simply tells the core to look to the max value (which a true result is greater than a false result) from any of the extensions that define ``stagesDiffer``. As with ``stagesDiffer`` you must also define a ``isModifiedOnDraft`` other wise again only ``Versioned``'s ``isModifiedOnDraft`` will be called and for example the "Modified" flag on pages in the page tree will not properly reflect the changes.

This class does not check if it's owner differs between stages.

```php
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
    return ($this->isOnDraft() && $this->stagesDiffer());
}
```
