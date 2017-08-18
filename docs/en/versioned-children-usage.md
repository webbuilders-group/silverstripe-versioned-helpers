Versioned Children Extension Usage
=================
The ``VersionedChildrenExtension`` provides hooks into the modified, publish, unpublish, etc actions of a versioned object to carry over into ``has_many`` relationships. This extension does not provide archive support, it's recommended that you implement this on your own in an ``onBeforeDelete`` method if needed. For example when working with pages this allows you to publish your versioned child object along with the page.

## Usage
To use this extension you must add the extension to your parent object for example say we have a class called ``HomePage`` and that class has a ``has_many`` relationship called ``CarouselItems``.

```php
<?php
class HomePage extends Page {
    private static $has_many=array(
        'CarouselItems'=>'HomeCarouselItem'
    );
}

class HomePage_Controller extends Page_Controller {}
```

```php
<?php
class HomeCarouselItem extends DataObject {
    private static $db=array(
        'Title'=>'Varchar(50)'
    );

    private static $has_one=array(
        'Image'=>'Image',
        'Parent'=>'HomePage'
    );

    private static $extensions=array(
        'Versioned'
    );
}
```

Right now the problem we have is that when the page is published, the carousel items are not so our carousel would not have any items on the live site. To solve this we need to add the ``VersionedChildrenExtension`` extension to the ``HomePage`` class. Adding the ``VersionedChildrenExtension`` requires passing in the name of each relationship that it needs to control to the constructor for the extension. For example:

#### PHP
```php
<?php
class HomePage extends Page {
    /* ... */
    private static $extensions=array(
        "VersionedChildrenExtension('CarouselItems')"
    );
}

/* ... */
```

#### YAML
```yml
HomePage:
    extensions:
        - "VersionedChildrenExtension('CarouselItems')"
```

If you have more than one relationship that has the ``Versioned`` extension then you can just comma separate more relationships for example ``VersionedChildrenExtension('CarouselItems','MyOtherRelation')``.

One last thing we need to deal with is to ensure that our child object has the necessary methods, the easiest way to do this is to add the [VersionedSupportExtension](versioned-support-usage.md). For example:

#### PHP
```php
<?php
class HomeCarouselItem extends DataObject {
    /* ... */

    private static $extensions=array(
        'Versioned',
        'VersionedSupportExtension'
    );
}
```

#### YAML
```yml
HomeCarouselItem:
    extensions:
        - "VersionedSupportExtension"
```

The methods defined in the ``VerionedSupportExtension`` allow the ``VersionedChildrenExtension`` to publish, unpublish, revert, etc as well as relay back to the parent object the modified and publish status of the child object. For example the relaying of the modified/publish status of the child object allows the "Save & Publish" button on pages to change from "Published" to "Save & Publish" and highlight it green.


## Revering the Parent to a Specific Version
Reverting the parent to a specific version does not work very well with children versioned objects since you do not get a new version number on the children when you modify the parent. In an attempt to accurately revert the children when you revert the parent to a specific version the ``VersionedChildrenExtension`` uses a time window and the closest time to the edit date of the parent. This time window can be adjusted by setting the ``VersionedChildrenExtension.archived_error_margin`` to a lower time in minutes. By default this is set to one minute, which means that somewhere within a minute of the last edit date for the parent there must be an edit of the child in order to rollback the child in tandem. If you want to effectively disable this set the value of the setting to 0.

```yml
VersionedChildrenExtension:
    archived_error_margin: 1
```


## User Experience Recommendation
It's recommended that you display the modified and published status in your GridField for managing the child objects. To do this add the ``IsModifiedOnStage`` and ``ExistsOnLive`` "fields" to your summary fields. You should probably also cast them to ``Boolean->Nice`` so you get a friendly Yes/No for the user rather than 1 and an empty column. For example:

```php
<?php
class HomeCarouselItem extends DataObject {
    private static $summary_fields=array(
        'Image.CMSThumbnail'=>'',
        'Title'=>'Title',
        'IsModifiedOnStage'=>'Modified',
        'ExistsOnLive'=>'Published'
    );
}
```

To cast these two fields to a friendly Yes/No you can use the ``GridFieldDataColumns::setFieldCasting()`` method, for example:

```php
<?php
class HomePage extends Page {
    /* ... */

    /**
     * Gets fields used in the cms
     * @return FieldList Fields to be used
     */
    public function getCMSFields() {
        $fields=parent::getCMSFields();


        $fields->addFieldToTab('Root.Carousel', $gridField=new GridField('CarouselImages', 'Carousel Images', $this->CarouselImages(), GridFieldConfig_RecordEditor::create(10)));

        $gridField->getConfig()
                            ->getComponentByType('GridFieldDataColumns')
                                    ->setFieldCasting(array(
                                                            'IsModifiedOnStage'=>'Boolean->Nice',
                                                            'ExistsOnLive'=>'Boolean->Nice'
                                                        ));


        return $fields;
    }
}

/* ... */
```
