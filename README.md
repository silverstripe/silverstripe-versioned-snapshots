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

This module enables the data model. To take full advantage of its core offering, you should install [silverstripe/versioned-snapshot-admin](https://github.com/silverstripe/silverstripe-versioned-snapshot-admin) to expose these snapshots through the "History" tab of the CMS.

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

* It adds significant overhead to all `DataObject::write()` calls. (Benchmarking TBD)
* `many_many` relationships **must use "through" objects**. (implicit many_many is not versionable)
* Snapshot history is *not retroactive*. You will lose all your version history and start new with
snapshot history. (A migration task would be a great contribution to this project!)

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

For CMS views, use the `SnapshotSiteTreeExtension` to provide notifications about
owned modification state (WORK IN PROGRESS, POC ONLY)

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
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class MySaveHandler extends SaveHandler
{
    protected function createSnapshot(EventContext $context): ?Snapshot
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

### Adding snapshot creators

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
            'formSubmitted.myFormHandler': true
          handler: %$MyProject\Handlers\MyHandler
```

Notice that the event name is in the key of the configuration. This makes it possible for another layer of
configuration to disable it. See below.

### Removing snapshot creators

To remove an event from a handler, simply set its value to `false`.

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Snapshots\Dispatch\Dispatcher:
    properties:
      handlers:
        myForm:
          on:
            'formSubmitted.myFormHandler': false
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
use SilverStripe\Snapshots\Dispatch\EventHandlerLoader;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Handler\Form\SaveHandler;

class MyEventLoader implements EventHandlerLoader
{
    public function addToDispatcher(Dispatcher $dispatcher): void
    {
        $dispatcher->removeListenerByClassName('formSubmitted.save', SaveHandler::class);
    }
}
```

### Snapshot creation API

To cover all cases, this module allows you to invoke snapshot creation in any part of your code outside of normal action flow.

When you want to create a snapshot just call `createSnapshotFromAction` function like this:

```
Snapshot::singleton()->createSnapshotFromAction($owner, $origin, $message, $objects);

```

`$owner` is the top level object which is seen as the owner of the action.
Most common case is that this object is the page. Owner object is mandatory.

`$origin` is the object which should be matching the action, i.e. the action is changing the origin object.
Valid values:

**Origin is different from the Owner**

This is the main and most common case. A snapshot will be created which references the changed origin object.

**Origin is the same as Owner**

This means that the changed object is the owner so for example the user edits the page.
Note that some actions may declare that they are editing the page but they may edit some nested objects (for example block reoreder).
There is a way to override this behaviour discussed in `Runtime overrides`.

**Origin is `null`**

This will be interpreted as the origin object was't possible to identify or it doesn't make sense to reference it.
If message is available the snapshot will be created with a special object called `event` which will take place of the missing origin object.

There are two main cases here:

`1` - snapshot module doesn't have enough information to find origin and is using `event` as a placeholder to fill the gap.
This happens mostly for custom CMS action which have arbitrary effect from the module point of view.
There is a way to override this behaviour discussed in `Runtime overrides`.

`2` - the changed object is not worth referencing - for example an action which is doing a batch write would reference too many objects (i.e. page import).
Instead of creating many snapshots for all individual changes you create one batch action with message which explains the details.

`$message` is the a context message which will be shown in the snapshot UI.
It should be used to provide additional context to the user about the snapshot as sometimes the referenced object may not provide enough information.

`$objects` is a an optional list of other objects that may be related to this action. Consider following example:


```
Snapshot::singleton()->createSnapshotFromAction($page, $block, 'something happened to a block', [$layoutBlock]);
```

Page is the owner, block is the origin and layout block is a related object.
Passing the layout block through allows the layout block to display it's own version history in the CMS edit form.
This feature may have marginal use and it's ok to skip it.


## Versioning

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
