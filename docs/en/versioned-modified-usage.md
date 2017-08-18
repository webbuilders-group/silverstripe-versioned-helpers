Versioned Modified Extension Usage
=================
The ``VersionedModifiedExtension`` provides the basis of the modified detection of children objects that the [VersionedChildrenSupport](versioned-children-usage.md) extension relies on. It provides the following methods:

* __getIsModifiedOnStage:__ Checks to see if the version numbers between the ``Stage`` and ``Live`` state of any of the versioned children differs.
* __stagesDiffer:__ This is basically a different approach to ``getIsModifiedOnStage`` though the result from both should be the same.
