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
     * @param $class
     * @param $id
     * @return string
     */
    public static function hashForSnapshot($class, $id)
    {
        return base64_encode(hash('sha256', sprintf('%s:%s', $class, $id), true));
    }

    /**
     * Generates a hash for the object for versioned snapshots
     *
     * @param DataObject $obj
     * @return string
     */
    public static function hashObjectForSnapshot(DataObject $obj)
    {
        return static::hashForSnapshot($obj->baseClass(), $obj->ID);
    }
}
