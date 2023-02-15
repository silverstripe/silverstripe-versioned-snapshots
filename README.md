## SilverStripe Versioned Snapshots

[![Build Status](https://api.travis-ci.org/silverstripe/silverstripe-versioned-snapshots.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-versioned-snapshots)
[![Latest Stable Version](https://poser.pugx.org/silverstripe/versioned/version.svg)](http://www.silverstripe.org/stable-download/)
[![Latest Unstable Version](https://poser.pugx.org/silverstripe/versioned-snapshots/v/unstable.svg)](https://packagist.org/packages/silverstripe/versioned-snapshots)
[![Total Downloads](https://poser.pugx.org/silverstripe/versioned-snapshots/downloads.svg)](https://packagist.org/packages/silverstripe/versioned-snapshots)
[![License](https://poser.pugx.org/silverstripe/versioned-snapshots/license.svg)](https://github.com/silverstripe/silverstripe-versioned-snapshots#license)
[![Dependency Status](https://www.versioneye.com/php/silverstripe:versioned-snapshots/badge.svg)](https://www.versioneye.com/php/silverstripe:versioned-snapshots)

## Overview

Enables snapshots for enhanced history and modification status for deeply nested ownership structures.
It's solving an [important UX issue](https://github.com/silverstripe/silverstripe-versioned/issues/195) with versioning,
which is particularly visible in [content blocks](https://github.com/dnadesign/silverstripe-elemental) implementations.

This module enables the data model for snapshots. To take full advantage of its core offering, you should install [silverstripe/versioned-snapshot-admin](https://github.com/silverstripe/silverstripe-versioned-snapshot-admin) to expose these snapshots through the "History" tab of the CMS.

WARNING: This module is experimental, and not considered stable.

## Installation

```
$ composer require silverstripe/versioned-snapshots
```

You'll also need to run `dev/build`.

## What does this do?

Imagine you have a content model that relies on an ownership structure, using the `$owns` setting.

```
BlockPage
  (has_many) Blocks
    (has_one) Gallery
       (many_many) Image
```

Ownership between each of those nodes affords publication of the entire graph through one commmand
(or click of a button). But it is not apparent to the user what owned content, if any, will
be published. If the Gallery is modified, `BlockPage` will not show a modified state.

This module aims to make these modification states and implicit edit history more transparent.

## What does it _not_ do?

Currently, rolling back a record that owns other content is not supported and will produce unexpected results.
Further, comparing owned changes between two versions of a parent is not supported.

## Can I use this in my current project?

Yes, with few caveats:

* `many_many` relationships **must use "through" objects**. (implicit many_many is not versionable)
* You will have to migrate all of your versioned content to snapshots (See [Migrating from versioned](#migrating-from-versioned))
* Some editing events may not be captured, particularly some provided by thirdparty modules. See ([Adding your own snapshot creator](#adding-your-own-snapshot-creator))
* Does not (yet) fully work with Postgres. Pull requests welcome!

## API

While the `SnapshotPublishable` extension offers a large API surface, there are only a few primary methods
that are relevant to the end user:

* `$myDataObject->hasOwnedModifications(): bool` returns true if the record owns records that have changes
* `$myDataObject->getPublishableObjects(): ArrayList`: returns a list of `DataObject` instances that will be published
along with the owner.
* `$myDataObject->getActivityFeed(): ArrayList` Provides a collection of objects that can be rendered
on a template to create a human-readable activity feed. Returns an array of `ActivityEntry` objects containing the following:
    * `Subject`: The `DataObject` record that instantiated the activity
    * `Action`: One of: `CREATED`, `MODIFIED`, `DELETED`, `ADDED`, or `REMOVED`.
    * `Owner`: Only defined in `many_many` reltionships. Provides information on what the record was
    linked to. Informs the `ADDED` and `REMOVED` actions.

## Extensions

The snapshot functionality is provided through the `SnapshotPublishable` extension, which
is a drop-in replacement for `RecursivePublishable`. By default, this module will replace
`RecursivePublishable`, which is added to all dataobjects by `silverstripe-versioned`, with
this custom subclass.

## How it works

Snapshots are created with handlers registered to user events in the CMS triggered by
the [`silverstripe/cms-events`](https://github.com/silverstripe/silverstripe-cms-events)
module.

### Customising the snapshot messages

By default, these events will trigger the message defined in the language file, e.g.
`_t('SilverStripe\Snapshots\Handler\Form\FormSubmissionHandler.HANDLER_publish', 'Publish page')`. However, if you want
to customise this message at the configuration level, simply override the message on the handler class.

```yaml
SilverStripe\Snapshots\Handler\Form\FormSubmissionHandler:
  messages:
    publish: 'My publish message'
```

In this case "publish" is the **action identifier** (the function that handles the form).

### Customising existing snapshot creators

All of the handlers are registered with injector, so the simplest way to customise them is to override their
definitions in the configuration.

For instance, if you have something custom you with a snapshot when a page is saved:

```php
use SilverStripe\Snapshots\Handler\Form\SaveHandler;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Snapshot;

class MySaveHandler extends SaveHandler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        //...
    }
}
```

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Snapshots\Handler\Form\SaveHandler:
    class: MyProject\MySaveHandler
```

### Adding your own snapshot creator

If you have custom actions or form handlers you've added to the CMS, you might want to either ensure their tracked
by the default snapshot creators, or maybe even build your own snapshot creator for them. In this case, you can
use the declarative API on `Dispatcher` to subscribe to the events you need.

Let's say we have a form that submits to a function: `public function myFormHandler($data, $form)`.

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Snapshots\Dispatch\Dispatcher:
    properties:
      handlers:
        myForm:
          on:
            - 'formSubmitted.myFormHandler'
          handler: %$MyProject\Handlers\MyHandler
```

Notice that the event name is in the key of the configuration. This makes it possible for another layer of
configuration to disable it. See below.

### Removing snapshot creators

To remove an event from a handler, simply add it to the `off` array.

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Snapshots\Dispatch\Dispatcher:
    properties:
      handlers:
        myForm:
          off:
            - 'formSubmitted.myFormHandler'
```

### Procedurally adding event handlers

You can register a `EventHandlerLoader` implementation with `Dispatcher` to procedurally register and unregister
events.

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Snapshots\Dispatch\Dispatcher:
    properties:
      loaders:
        myLoader: %$MyProject\MyEventLoader
```

```php
use SilverStripe\Snapshots\Dispatch\DispatcherLoaderInterface;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Handler\Form\SaveHandler;

class MyEventLoader implements DispatcherLoaderInterface
{
    public function addToDispatcher(Dispatcher $dispatcher): void
    {
        $dispatcher->removeListenerByClassName('formSubmitted.save', SaveHandler::class);
    }
}
```

### Snapshot creation API

To cover all cases, this module allows you to invoke snapshot creation in any part of your code outside of normal action flow.

When you want to create a snapshot just call `createSnapshot` function like this:

```php
Snapshot::singleton()->createSnapshot(DataObject $origin, array $extraObjects = []);
```

`$origin` is the object which should be matching the action, i.e. the action is changing the origin object.

`$extraObjects` is an array of extra dataobjects you want to be in the snapshot. Every call to `createSnapshot` implicitly includes the following records in addition to the origin:
  * All of the records the origin is "owned" by, e.g. `BlockImage > BaseElement > ElementalArea > Page`
  * All of the records the origin has _implicitly modified_. (See [Implicit modifications](#implicit-modifications))

## When there is no "origin"

Some modifications to your content aren't necessarily triggered by editing event to a specific entity. For these cases, you can use the `createSnapshotFromEvent` API.

```php
Snapshot::singleton()->createSnapshotFromEvent('Description of event');
```

Examples of generic events include reordering the site tree, copying translations, importing content, and more. Think of it as a simple "git commit" message for your content. It creates a marker on your timeline that content editors can refer back to at some point in the future.


## Implicit modifications

Sometimes edits to the record that appears to be the "origin" are implicitly edits to other records. The most common case of this is adding related records. If a user makes a change to a `CheckboxSetField` that manages a `many_many` relation, for instance, the record that displays those checkboxes remains unchanged and does not merit a new version. The addition or removal of new related records, however,
does merit a new snapshot as the ownership chain has been updated.

The `createSnapshot` API is aware of these kinds of modifications, and attempts to detect them using the `RelationDiffer` service. When a modification includes changes to relationships, `createSnapshot` will fallback to create a generic event that describes what changes happened, for instance: `'Added two categories'`.

This relation diffing is expensive to run on every save for every relationship, however, and therefore, you need to opt-in to it using the `$snapshot_relation_tracking` setting.

```php
class Product extends DataObject
{
    private static $many_many = [
        'Categories' => Category::class,
    ];

    private static $snapshot_relation_tracking = ['Categories'];

}
```

Another common example of implicit modifications is the `ElementalEditor` field in [silverstripe-elemental](https://github.com/dnadesign/silverstripe-elemental) version 4.x. When the page is saved, it actually saves all the blocks in the editor, which are `has_many` relations. Because it is such a common use case,  blocks are tracked in `snapshot_relation_tracking` by default, so that page saves will result in "Modified/added/deleted block" snapshots where appropriate.


## Migrating from Versioned

To migrate all your `_versions` tables to snapshots, use the `snapshot-migration` task:

```
$ vendor/bin/sake dev/tasks/snapshot-migration
```

Alternatively, this task is available as a [queued job](https://github.com/symbiote/silverstripe-queuedjobs).

The task should be fairly low-impact, as it only writes to the new (and presumably empty) snapshots tables. It should also perform at scale, since it doesn't do any processing of the records in PHP. The migration is pure SQL.

## Thirdparty module support

Some common thirdparty modules are supported out of the box. The most notable is [silverstripe-elemental](https://github.com/dnadesign/silverstripe-elemental), which has several specific snapshot creators installed by default, including:

* Archive element
* Save individual element
* Create element (GraphQL query)
* Edit individual element
* Save all elements via page save
* Sort elements
* ModelAdmin and GridField CSV imports

As mentioned above, elements all receive `snapshot_relation_tracking` on their pages by default, as well.

Another module that is supported out of the box is [GridFieldExtensions](https://github.com/symbiote/silverstripe-gridfieldextensions). A handler is provided
for its `GridFieldOrderableRows` component.

## Localisation

This module can be configured to work with the [Fluent](https://github.com/tractorcow-farm/silverstripe-fluent) module.
Following the paradigm set by the Fluent version history, we do not allow any content inheritance when it comes to versioned history.
Our `Snapshot` and `SnpashotItem` models represent a more detailed version history, so we need to apply the following configuration to comply with the Fluent paradigm:

```yaml
SilverStripe\Snapshots\Snapshot:
    cms_localisation_required: 'exact'
    frontend_publish_required: 'exact'
    extensions:
        - TractorCow\Fluent\Extension\FluentExtension
    translate:
        - OriginHash

SilverStripe\Snapshots\SnapshotItem:
    cms_localisation_required: 'exact'
    frontend_publish_required: 'exact'
    extensions:
        - TractorCow\Fluent\Extension\FluentExtension
    translate:
        - ObjectHash
```

## Upgrading to 1.x.x

`1.x.x` release contains a couple of breaking changes.
We provide upgrade path for both.

### Object version DB field rename

DB field `Version` on `SnapshotItem` was renamed to `ObjectVersion` to prevent naming conflicts.
Please follow the steps below to upgrade.

* run `composer update` to upgrade to the desired `1.x.x` version of this module
* run `dev/build flush=all`
* run `dev/tasks/migrate-object-version-task`, run via CLI

### Legacy Fluent setup

This is relevant only for project which use [Fluent module](https://github.com/tractorcow-farm/silverstripe-fluent) and use localised snapshot models.

* run `composer update` to upgrade to the desired `1.x.x` version of this module
* review and update your Fluent configuration as per **Localisation** section of this readme
* run `dev/build flush=all`
* run `dev/tasks/migrate-fluent-object-hash-task`, run via CLI

### Recalculate hashes

Object hashes may be out of date.
It's recommended to update them otherwise pre-update history items may not show in the history viewer.
Run `dev/tasks/recalculate-hashes-task`, run via CLI

This dev task supports Fluent out of the box,

## Semantic versioning

This library follows [Semver](http://semver.org). According to Semver,
you will be able to upgrade to any minor or patch version of this library
without any breaking changes to the public API. Semver also requires that
we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All
other methods are not part of the public API. Where possible, we'll try
to keep `protected` methods backwards-compatible in minor/patch versions,
but if you're overriding methods then please test your work before upgrading.

## Reporting Issues

Please [create an issue](http://github.com/silverstripe/silverstripe-versioned-snapshots/issues)
for any bugs you've found, or features you're missing.

## License

This module is released under the [BSD 3-Clause License](LICENSE)
