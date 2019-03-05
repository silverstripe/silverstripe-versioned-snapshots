<?php


namespace SilverStripe\Snapshots;


use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

/**
 * Class SnapshotChangeSetItem
 * @property ChangeSetItem $owner
 */
class SnapshotChangeSetItem extends DataExtension
{
    public function updateChangeType(&$type, $draftVersion, $liveVersion)
    {
        /* @var DataObject|SnapshotPublishable $obj */
        $obj = $this->owner->getComponent('Object');

        if (!$obj->hasExtension(SnapshotPublishable::class)) {
            return;
        }

        if ($obj->hasOwnedModifications()) {
            $type = ChangeSetItem::CHANGE_MODIFIED;
        }
    }


}