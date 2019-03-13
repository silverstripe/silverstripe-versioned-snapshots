<?php


namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataExtension;

class SnapshotSiteTreeExtension extends DataExtension
{
    public function updateCMSFields($fields)
    {
        if (!$this->owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }
    }
}
