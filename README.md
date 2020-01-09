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

This module comes with two very different work flows.

* CMS action work flow (default) - trigger is on user action, actions are opt-in with some core actions already available
* model work flow - trigger is on after model write, actions are opt-out

### CMS action work flow

#### Static configuration

This module comes with some CMS actions already provided. This configuration is located in `config.yml` under `snapshot-actions`.
The format is very simple:

'`identifier`': '`message`'

Where `identifier` is the action identifier (this is internal name from the component which is responsible for handling the action).
For example for page edit form we have the following rule:

'`save`': '`Save page`'

This means each time a user saves a page via page edit form a snapshot will be created with a context message `Save page`.

This configuration can be overridden via standard configuration API means.

**I want to add more actions**

Create following configuration in your project `_config` folder:

```
Name: snapshot-custom-actions
After:
  - '#snapshot-actions'
---
SilverStripe\Snapshots\Snapshot:
  actions:
    # grid field actions (via standard action)
    'togglelayoutwidth': 'Toggle layout width'
```

This will add a new action for the `togglelayoutwidth` action and the snapshot message for this action will be `Toggle layout width`.

**I want to disable a default action**

```
Name: snapshot-custom-actions
After:
  - '#snapshot-actions'
---
SilverStripe\Snapshots\Snapshot:
  actions:
    # GraphQL CRUD - disable default
    'graphql_crud_create': null
```

This will disable the action `graphql_crud_create` so no snapshot will be created when this action is executed.

**I want to add a action but with no message**

```
Name: snapshot-custom-actions
After:
  - '#snapshot-actions'
---
SilverStripe\Snapshots\Snapshot:
  actions:
    # grid field actions (via standard action)
    'togglelayoutwidth': ''
```

This will still create a snapshot for the action but no snapshot message will be displayed.

**I want to change message of existing action**

```
Name: snapshot-custom-actions
After:
  - '#snapshot-actions'
---
SilverStripe\Snapshots\Snapshot:
  actions:
    # GraphQL CRUD - disable default
    'graphql_crud_create': 'My custom message'
```

This will create snapshot for the action with your custom message.
Setting empty string as a message will still create the snapshot but with no message.

#### How to find your action identifier

Common case is where you want to add a new action configuration but you don't know what your action identifier is.
This really depends on what the component responsible for handling the action is.
The most basic approach is to add temporary logging to start of `SilverStripe\Snapshots\Snapshot::getActionMessage()`.
Every action which is covered by this module (regardless of the configuration) flows through this function.

```
public function getActionMessage($identifier): ?string
{
    error_log($identifier);
```

When the logging is in place you just go to the CMS and perform the action you are interested in.
This should narrow the list of identifier down to a much smaller subset.

#### Runtime overrides

In case static configuration in not enough, runtime overrides are available. This module comes with following types of listeners:

* Form submissions - actions that comes via form submissions (for example page edit form)
* GraphQL general - actions executed via GraphQL CRUD (for example standard model mutation)
* GraphQL custom - actions executed via GraphQL API (for example custom mutation)
* GridField alter - actions which are implemented via `GridField_ActionProvider` (for example delete item via GridField)
* GridField URL handler - actions which are implemented via `GridField_URLHandler`
* Page `CMSMain` actions - this covers page actions which are now handled by form submissions

Each type of listener provides an extension point which allows the override of the default module behaviour.

To apply your override you need to first know which listener is handling your action.
Sometimes you can guess based on the action category but using logging may help you determine the listener type more easily.

Form submissions - `Form\Submission::processAction`

GraphQL custom - `GraphQL\CustomAction::onAfterCallMiddleware`

GraphQL general - `GraphQL\GenericAction::afterMutation`

GridField alter - `GridField\AlterAction::afterCallActionHandler`

GridField URL handler - `GridField\UrlHandlerAction::afterCallActionURLHandler`

Page `CMSMain` actions - `Page\CMSMainAction::afterCallActionHandler`

Once you know listener type and the action identifier you need to create an extension which is a subclass of one of the abstract listener handlers.
Abstract listener depends on your listener type.

Form submissions - `Form\SubmissionListenerAbstract`

GraphQL custom - `GraphQL\CustomActionListenerAbstract`

GraphQL general - `GraphQL\GenericActionListenerAbstract`

GridField alter - `GridField\AlterActionListenerAbstract`

GridField URL handler - `GridField\UrlHandlerActionListenerAbstract`

Page `CMSMain` actions - `Page\CMSMainListenerAbstract`

**Example implementation**

config

```
SilverStripe\Snapshots\Snapshot:
  extensions:
    - App\Snapshots\Listener\MutationUpdateLayoutBlockGroup
```

extension

```
<?php

namespace App\Snapshots\Listener;

use App\Models\Blocks\LayoutBlock;
use GraphQL\Type\Schema;
use Page;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Listener\GraphQL\CustomActionListenerAbstract;

/**
 * Class MutationUpdateLayoutBlockGroup
 *
 * @property Snapshot|$this $owner
 * @package App\Snapshots\Listener
 */
class MutationUpdateLayoutBlockGroup extends CustomActionListenerAbstract
{
    protected function getActionName(): string
    {
        return 'mutation_updateLayoutBlockGroup';
    }

    /**
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param Schema $schema
     * @param string $query
     * @param array $context
     * @param array $params
     * @return bool
     * @throws ValidationException
     */
    protected function processAction(
        Page $page,
        string $action,
        string $message,
        Schema $schema,
        string $query,
        array $context,
        array $params
    ): bool {
        if (!array_key_exists('block', $params)) {
            return false;
        }

        $data = $params['block'];

        if (!array_key_exists('ID', $data)) {
            return false;
        }

        $blockId = (int) $data['ID'];

        if (!$blockId) {
            return false;
        }

        $block = LayoutBlock::get_by_id($blockId);

        if ($block === null || !$block->exists()) {
            return false;
        }

        Snapshot::singleton()->createSnapshotFromAction($page, $block, $message);

        return true;
    }
}

```

`getActionName` is the action identifier

`CustomActionListenerAbstract` is the parent class because this action is a custom mutation

Returning `false` inside `processAction` makes the module fallback to default behaviour.

Returning `true` inside `processAction` makes the module skip the default behaviour.

If you return `true` it's up to you to create the snapshot.
This covers the case where the action uses custom data and it's impossible for the module to figure out the origin object.
Use this approach when you are unhappy with the default behaviour and you know the way how to find the origin object from the data.
Note that the context data available is different for each listener type as the context is different.

#### Snapshot creation API

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


### Model work flow

When a dataobject is written, an `onAfterWrite` handler opens a snapshot by writing
a new `VersionedSnapshot` record. As long as this snapshot is open, any successive dataobject
writes will add themselves to the open snapshot, on the `VersionedSnapshotItem` table. The dataobject
that opens the snapshot is stored as the `Origin` on the `VersionedSnapshot` table (a polymorphic `has_one`).
It then looks up the ownership chain using `findOwners()` and puts each of its owners into the snapshot.

Each snapshot item contains its version, class, and ID at the time of the snapshot. This
provides enough information to query what snapshots a given dataobject was involved in since
a given version or date.

For the most part, the snapshot tables are considered immutable historical records, but there
are a few cases when snapshots are retroactively updated

* When changes are reverted to live, any snapshots those changes made are deleted.
* When the ownership structure is changed, the previous owners are surgically removed
from the graph and the new ones stitched in.



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
