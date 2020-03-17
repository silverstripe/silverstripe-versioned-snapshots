<?php

namespace SilverStripe\Snapshots;

use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use Exception;

class ActivityEntry extends ArrayData
{
    const MODIFIED = 'MODIFIED';

    const DELETED = 'DELETED';

    const CREATED = 'CREATED';

    const ADDED = 'ADDED';

    const REMOVED = 'REMOVED';

    const PUBLISHED = 'PUBLISHED';

    const UNPUBLISHED = 'UNPUBLISHED';

    public static function createFromSnapshotItem(SnapshotItem $item)
    {
        $itemObj = $item->getItem();

        if ($itemObj !== null && $itemObj instanceof SnapshotEvent) {
            $flag = null;
        } elseif ($item->WasPublished) {
            $flag = self::PUBLISHED;
        } elseif ($item->Parent()->exists()) {
            $flag = $item->WasDeleted ? self::REMOVED : self::ADDED;
        } elseif ($item->WasDeleted) {
            $flag = self::DELETED;
        } elseif ($item->WasUnpublished) {
            $flag = self::UNPUBLISHED;
        } elseif ($item->WasCreated) {
            $flag = self::CREATED;
        } else {
            $flag = self::MODIFIED;
        }

        // If the items been deleted then we want to get the last version of it
        if ($itemObj === null) {
            // This gets all versions except for the deleted version so we just get the latest one
            $previousVersion = Versioned::get_all_versions($item->ObjectClass, $item->ObjectID)
                ->sort('Version', 'DESC')
                ->first();
            if ($previousVersion && $previousVersion->exists()) {
                $itemObj = $item->getItem($previousVersion->Version);
            // This is to deal with the case in which there is no previous version
            // it's better to give a faulty snapshot point than break the app
            } elseif ($item->Version > 1) {
                $itemObj = $item->getItem($item->Version - 1);
            }
        }

        if (!$itemObj) {
            throw new Exception(sprintf(
                'Could not resolve SnapshotItem %s to a previous %s version',
                $item->ID,
                $item->ObjectClass
            ));
        }

        return new static([
            'Subject' => $itemObj,
            'Action' => $flag,
            'Owner' => null,
            'Date' => $item->obj('Created')->Nice(),
        ]);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->Subject instanceof SnapshotEvent && $this->Subject->Title) {
            return $this->Subject->Title;
        }

        return ucfirst(sprintf(
            '%s "%s"',
            $this->Subject->singular_name(),
            $this->Subject->getTitle()
        ));
    }
}
