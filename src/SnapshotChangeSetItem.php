<?php

namespace SilverStripe\Snapshots;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\ChangeSetItem;

/**
 * Customise @see ChangeSetItem to use snapshot records instead of versioned records
 *
 * @extends Extension<ChangeSetItem>
 */
class SnapshotChangeSetItem extends Extension
{
    /**
     * Extension point in @see ChangeSetItem::getChangeType()
     *
     * @param $type
     * @param $draftVersion
     * @param $liveVersion
     * @throws Exception
     */
    protected function updateChangeType(&$type, $draftVersion, $liveVersion): void
    {
        $owner = $this->getOwner();

        /** @var DataObject|SnapshotPublishable $obj */
        $obj = $owner->getComponent('Object');

        if (!$obj->hasExtension(SnapshotPublishable::class)) {
            return;
        }

        if (!$obj->hasOwnedModifications()) {
            return;
        }

        $type = ChangeSetItem::CHANGE_MODIFIED;
    }
}
