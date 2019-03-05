<?php


namespace SilverStripe\Snapshots;

use SilverStripe\Versioned\Versioned_Version;
use SilverStripe\View\ArrayData;

class ActivityEntry extends ArrayData
{
    const MODIFIED = 'MODIFIED';

    const DELETED = 'DELETED';

    const CREATED = 'CREATED';

    const ADDED = 'ADDED';

    public static function createFromSnapshotItem(SnapshotItem $item)
    {
        if ($item->LinkedToObject()->exists()) {
            return new static([
                'Subject' => $item->LinkedToObject(),
                'Action' => self::ADDED,
                'Owner' => $item->LinkedFromObject(),
            ]);
        }

        $flag = null;
        if ($item->Version == 1) {
            $flag = self::CREATED;
        } else {
            if ($item->WasDeleted) {
                $flag = self::DELETED;
            } else {
                $flag = self::MODIFIED;
            }
        }

        return new static([
            'Subject' => $item,
            'Action' => $flag,
            'Owner' => null,
        ]);
    }
}
