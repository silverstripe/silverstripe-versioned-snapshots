<?php

namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Snapshot event is used as a stand-in placeholder for snapshots that do not have their own origin
 *
 * @mixin Versioned
 */
class SnapshotEvent extends DataObject
{
    private static array $extensions = [
        Versioned::class,
    ];

    private static string $table_name = 'VersionedSnapshotEvent';

    private static string $singular_name = 'snapshot event';

    private static string $plural_name = 'snapshot events';

    private static string $class_description = 'Snapshot event is used as a stand-in placeholder for '
    . 'snapshots that do not have their own origin';

    private static array $db = [
        'Title' => 'Varchar',
    ];
}
