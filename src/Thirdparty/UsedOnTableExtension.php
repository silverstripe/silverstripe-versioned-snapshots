<?php

namespace SilverStripe\Snapshots\Thirdparty;

use SilverStripe\Admin\Forms\UsedOnTable;
use SilverStripe\Core\Extension;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotItem;

/**
 * Asset usage customisation (exclude snapshot related data to be shown in asset usage)
 *
 * @extends Extension<UsedOnTable>
 */
class UsedOnTableExtension extends Extension
{
    /**
     * Exclude snapshot data objects from appearing in Used On tab in Files section
     * Extension point in @see UsedOnTable::usage()
     *
     * @param array $excludedClasses
     */
    protected function updateUsageExcludedClasses(array &$excludedClasses): void
    {
        $excludedClasses[] = Snapshot::class;
        $excludedClasses[] = SnapshotItem::class;
        $excludedClasses[] = SnapshotEvent::class;
    }
}
