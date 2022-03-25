<?php

namespace SilverStripe\Snapshots;

use Exception;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\ChangeSetItem;

/**
 * Class SnapshotChangeSetItem
 *
 * @property ChangeSetItem $owner
 */
class SnapshotChangeSetItem extends DataExtension
{
    /**
     * Extension point in @see ChangeSetItem::getChangeType()
     *
     * @param $type
     * @param $draftVersion
     * @param $liveVersion
     * @throws Exception
     */
    public function updateChangeType(&$type, $draftVersion, $liveVersion): void
    {
        /** @var DataObject|SnapshotPublishable $obj */
        $obj = $this->owner->getComponent('Object');

        if (!$obj->hasExtension(SnapshotPublishable::class)) {
            return;
        }

        if (!$obj->hasOwnedModifications()) {
            return;
        }

        $type = ChangeSetItem::CHANGE_MODIFIED;
    }
}
