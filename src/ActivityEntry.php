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

    public static function createFromSnapshotItem(SnapshotItem $item)
    {
        if ($item->LinkedToObject()->exists()) {
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
        } elseif ($item->Version == 1) {
            $flag = self::CREATED;
        } else {
            $flag = self::MODIFIED;
        }

        return new static([
            'Subject' => $item->getItem(),
            'Action' => $flag,
            'Owner' => null,
            'Date' => $item->obj('Created')->Nice(),
        ]);
    }
}
