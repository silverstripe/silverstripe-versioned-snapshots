<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Base join class to ensure through objects can be subclasses of things other than DataObject
 */
class BaseJoin extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class,
    ];
}
