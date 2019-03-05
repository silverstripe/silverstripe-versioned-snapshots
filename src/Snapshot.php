<?php


namespace SilverStripe\Snapshots;


use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

class Snapshot extends DataObject
{
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
        $this->OriginHash = static::hash($this->OriginClass, $this->OriginID);
    }

}
