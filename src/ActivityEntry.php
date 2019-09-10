<?php

namespace SilverStripe\Snapshots;

use SilverStripe\View\ArrayData;

class ActivityEntry extends ArrayData
{
    const MODIFIED = 'MODIFIED';

    const DELETED = 'DELETED';

    const CREATED = 'CREATED';

    const ADDED = 'ADDED';

    const REMOVED = 'REMOVED';

    const PUBLISHED = 'PUBLISHED';

    public static function createFromSnapshotItem(SnapshotItem $item)
    {
        if ($item->LinkedToObjectID > 0 && $item->LinkedToObject()->exists()) {
            return new static([
                'Subject' => $item->LinkedToObject(),
                'Action' => $item->WasDeleted ? self::REMOVED : self::ADDED,
                'Owner' => $item->LinkedFromObject(),
                'Date' => $item->obj('Created')->Nice(),
            ]);
        }

        $flag = null;
        if ($item->WasDeleted) {
            $flag = self::DELETED;
        } elseif ($item->WasPublished) {
            $flag = self::PUBLISHED;
        } elseif ($item->Version == 1) {
            $flag = self::CREATED;
        } else {
            $flag = self::MODIFIED;
        }

        // The item is used to show the user a title etc for the item
        // Since the deleted version doesn't have that we want the prior version
        if ($item->WasDeleted && $item->Version > 1) {
            $itemObj = $item->getItem($item->Version - 1);
        } else {
            $itemObj = $item->getItem();
        }

        return new static([
            'Subject' => $itemObj,
            'Action' => $flag,
            'Owner' => null,
            'Date' => $item->obj('Created')->Nice(),
        ]);
    }
}
