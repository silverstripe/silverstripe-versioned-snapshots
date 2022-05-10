<?php

namespace SilverStripe\Snapshots;

use Exception;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;

/**
 * @property DataObject $Subject
 */
class ActivityEntry extends ArrayData
{
    const MODIFIED = 'MODIFIED';

    const DELETED = 'DELETED';

    const CREATED = 'CREATED';

    const ADDED = 'ADDED';

    const REMOVED = 'REMOVED';

    const PUBLISHED = 'PUBLISHED';

    const UNPUBLISHED = 'UNPUBLISHED';

    public static function createFromSnapshotItem(SnapshotItem $item): self
    {
        /** @var DataObject|Versioned $itemObj */
        $itemObj = $item->getItem();

        if ($itemObj instanceof SnapshotEvent) {
            $flag = null;
        } elseif ($item->WasPublished) {
            $flag = self::PUBLISHED;
        } elseif ($item->Parent()->exists()) {
            $flag = $item->WasDeleted
                ? self::REMOVED
                : self::ADDED;
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
        if ($itemObj === null || $itemObj->WasDeleted) {
            // This gets all versions except for the deleted version so we just get the latest one
            /** @var DataObject|Versioned $previousVersion */
            $previousVersion = Versioned::get_all_versions($item->ObjectClass, $item->ObjectID)
                ->sort('Version', 'DESC')
                ->find('WasDeleted', 0);

            if ($previousVersion && $previousVersion->exists()) {
                $itemObj = $item->getItem($previousVersion->Version);
            // This is to deal with the case in which there is no previous version
            // it's better to give a faulty snapshot point than break the app
            } elseif ($item->ObjectVersion > 1) {
                $itemObj = $item->getItem($item->ObjectVersion - 1);
            }
        }

        if (!$itemObj) {
            throw new Exception(sprintf(
                'Could not resolve SnapshotItem %s to a previous %s version',
                $item->ID,
                $item->ObjectClass
            ));
        }

        return static::create([
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
