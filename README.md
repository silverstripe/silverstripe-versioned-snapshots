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

This module enables the data model, you might also be interested in [silverstripe/versioned-snapshot-admin](https://github.com/silverstripe/silverstripe-versioned-snapshot-admin) to expose these snapshots through the "History" tab of the CMS.

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

## Caveats

* Adds significant overhead to all `DataObject::write()` calls. (Benchmarking TBD)
* `many_many` relationships **must use "through" objects**. 

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
