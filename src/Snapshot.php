<?php


namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;

class Snapshot extends DataObject
{
    use SnapshotHasher;

    private static $db = [
        'OriginHash' => 'Varchar(64)',
    ];

    private static $has_one = [
        'Origin' => DataObject::class,
        'Author' => Member::class,
    ];

    private static $has_many = [
        'Items' => SnapshotItem::class,
    ];

    private static $indexes = [
        'OriginHash' => true,
    ];

    private static $table_name = 'VersionedSnapshot';

    private static $singular_name = 'Snapshot';

    private static $plural_name = 'Snapshots';

    private static $default_sort = 'ID ASC';

    private static $cascade_deletes = [
        'Items',
    ];

    public function getOriginItem()
    {
        return $this->Items()->filter([
            'ObjectHash' => $this->OriginHash,
        ])->first();
    }

    public function getOriginVersion()
    {
        $originItem = $this->getOriginItem();
        if ($originItem) {
            return Versioned::get_version(
                $originItem->ObjectClass,
                $originItem->ObjectID,
                $originItem->Version
            );
        }

        return null;
    }

    public function getDate()
    {
        return $this->LastEdited;
    }

    public function getActivityDescription()
    {
        $item = $this->getOriginItem();
        if ($item) {
            $activity = ActivityEntry::createFromSnapshotItem($item);
            return ucfirst(sprintf(
                '%s "%s"',
                $activity->Subject->singular_name(),
                $activity->Subject->getTitle()
            ));
        }

        return 'none';
    }

    public function getActivityType()
    {
        $item = $this->getOriginItem();
        if ($item) {
            $activity = ActivityEntry::createFromSnapshotItem($item);

            return $activity->Action;
        }

        return '';
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    public function canView($member = null, $context = [])
    {
        return true;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->OriginHash = static::hashForSnapshot($this->OriginClass, $this->OriginID);
    }
}
