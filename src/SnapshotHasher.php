<?php

namespace SilverStripe\Snapshots;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;

/**
 * Implements hashing functionality for versioned snapshots
 */
trait SnapshotHasher
{
    /**
     * Generates a hash for versioned snapshots
     *
     * @param $class
     * @param $id
     * @return string
     */
    public static function hashForSnapshot($class, $id): string
    {
        // test code. remove.
        return ClassInfo::shortName($class) . '#' . $id;
        return base64_encode(hash('sha256', sprintf('%s:%s', $class, $id), true));
    }

    /**
     * Generates a hash for the object for versioned snapshots
     *
     * @param DataObject $obj
     * @return string
     */
    public static function hashObjectForSnapshot(DataObject $obj): string
    {
        return static::hashForSnapshot($obj->baseClass(), $obj->ID);
    }

    /**
     * @param DataObject $obj1
     * @param DataObject $obj2
     * @return bool
     */
    public static function hashSnapshotCompare(DataObject $obj1, DataObject $obj2): bool
    {
        return static::hashObjectForSnapshot($obj1) === static::hashObjectForSnapshot($obj2);
    }
}
