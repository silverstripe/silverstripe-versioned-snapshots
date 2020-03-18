<?php

namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class SnapshotEvent
 *
 * snapshot event is used as a stand-in placeholder for snapshots that do not have their own origin
 *
 * @package SilverStripe\Snapshots
 */
class SnapshotEvent extends DataObject
{
    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class,
    ];

    /**
     * @var string
     */
    private static $table_name = 'VersionedSnapshotEvent';

    /**
     * @var string
     */
    private static $singular_name = 'snapshot event';

    /**
     * @var string
     */
    private static $plural_name = 'snapshot events';

    /**
     * @var string
     */
    private static $description = 'Snapshot event';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
    ];
}
