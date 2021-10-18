<?php

namespace SilverStripe\Snapshots\Thirdparty;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotItem;

class UsedOnTableExtension extends DataExtension
{
    /**
     * Exclude snapshot data objects from appearing in Used On tab in Files section
     *
     * @param array $excludedClasses
     */
    public function updateUsageExcludedClasses(array &$excludedClasses): void
    {
        $excludedClasses[] = Snapshot::class;
        $excludedClasses[] = SnapshotItem::class;
        $excludedClasses[] = SnapshotEvent::class;
    }
}
