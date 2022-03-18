<?php

namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;

/**
 * Implements hashing functionality for versioned snapshots
 */
trait SnapshotHasher
{
    /**
     * Generates a hash for versioned snapshots
     *
     * @param string|null $class
     * @param int|null $id
     * @return string
     */
    public static function hashForSnapshot(?string $class, ?int $id): string
    {
        return md5(sprintf('%s:%s', $class, $id));
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
