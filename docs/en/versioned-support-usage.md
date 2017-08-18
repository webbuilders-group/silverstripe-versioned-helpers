Versioned Support Extension Usage
=================
The ``VersionedSupportExtension`` provides helpful common methods for dealing with versioned objects. It provides the following methods:

* __getIsModifiedOnStage:__ Checks to see if the version numbers between the ``Stage`` and ``Live`` state of the versioned object differs.
* __getIsDeletedFromStage:__ Checks to see if there is not a version number on the ``State`` state for the object.
* __getExistsOnLive:__ Checks to see if there is a version number on the ``Live`` state for the object.
