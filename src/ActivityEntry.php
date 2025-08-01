<?php

namespace SilverStripe\Snapshots;

use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;

/**
 * In-memory data which surfaces information about snapshot even activity
 *
 * @property DataObject|null $Subject
 * @property string|null $Action
 * @property string|null $Owner
 * @property string $Date
 */
class ActivityEntry extends ArrayData
{
    public const string MODIFIED = 'MODIFIED';

    public const string DELETED = 'DELETED';

    public const string CREATED = 'CREATED';

    public const string ADDED = 'ADDED';

    public const string REMOVED = 'REMOVED';

    public const string PUBLISHED = 'PUBLISHED';

    public const string UNPUBLISHED = 'UNPUBLISHED';

    public function createFromSnapshotItem(SnapshotItem $item): ?ActivityEntry
    {
        /** @var DataObject|Versioned $itemObj */
        $itemObj = $item->getItem();

        if ($itemObj instanceof SnapshotEvent) {
            $flag = null;
        } elseif ($item->WasPublished) {
            $flag = ActivityEntry::PUBLISHED;
        } elseif ($item->Parent()->exists()) {
            $flag = $item->WasDeleted
                ? ActivityEntry::REMOVED
                : ActivityEntry::ADDED;
        } elseif ($item->WasDeleted) {
            $flag = ActivityEntry::DELETED;
        } elseif ($item->WasUnpublished) {
            $flag = ActivityEntry::UNPUBLISHED;
        } elseif ($item->WasCreated) {
            $flag = ActivityEntry::CREATED;
        } else {
            $flag = ActivityEntry::MODIFIED;
        }

        // If the items been deleted then we want to get the last version of it
        if ($itemObj === null || $itemObj->WasDeleted) {
            $singleton = DataObject::singleton($item->ObjectClass);

            if ($singleton->hasExtension(Versioned::class)) {
                // Item is versioned - find the previous version
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
            } else {
                // Item is not versioned - use the singleton as stand-in
                $itemObj = $singleton;
            }
        }

        if (!$itemObj) {
            // We couldn't compose the activity entry from available versioned history records
            // This is a valid case as some projects opt to delete some of their older versioned records
            return null;
        }

        /** @var DBDatetime $createdField */
        $createdField = $item->obj('Created');

        return static::create([
            'Subject' => $itemObj,
            'Action' => $flag,
            'Owner' => null,
            'Date' => $createdField->Nice(),
        ]);
    }

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
