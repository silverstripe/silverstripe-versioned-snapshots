<?php

namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Snapshot event is used as a stand-in placeholder for snapshots that do not have their own origin
 * as well as a holder for custom events (custom message)
 */
class SnapshotEvent extends DataObject
{
    private static array $extensions = [
        Versioned::class,
    ];

    private static string $table_name = 'VersionedSnapshotEvent';

    private static string $singular_name = 'Custom event';

    private static string $plural_name = 'Custom events';

    private static string $description = 'Holder for a custom event which has a custom message';

    private static array $db = [
        'Title' => 'Varchar',
    ];
}
