<?php


namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\Versioned;

class SnapshotItem extends DataObject
{
    use SnapshotHasher;

    private static $db = [
        'Version' => 'Int',
        'WasPublished' => 'Boolean',
        'WasDraft' => 'Boolean',
        'WasDeleted' => 'Boolean',
        'ObjectHash' => 'Varchar(64)',
    ];

    private static $has_one = [
        'Snapshot' => Snapshot::class,
        'Object' => DataObject::class,
        'LinkedFromObject' => DataObject::class,
        'LinkedToObject' => DataObject::class,
    ];

    private static $indexes = [
        'Version' => true,
        'ObjectHash' => true,
        'Object' => [
            'type' => 'unique',
            'columns' => ['ObjectHash', 'Version', 'SnapshotID']
        ]
    ];

    private static $table_name = 'VersionedSnapshotItem';

    private static $singular_name = 'SnapshotItem';

    private static $plural_name = 'SnapshotItems';

    private static $default_sort = 'ID ASC';


    public function canView($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    public function canEdit($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->can(__FUNCTION__, $member, $context);
    }

    public function canDelete($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    /**
     * Default permissions for this ChangeSetItem
     *
     * @param string $perm
     * @param Member $member
     * @param array $context
     * @return bool
     */
    public function can($perm, $member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // Allow extensions to bypass default permissions, but only if
        // each change can be individually published.
        $extended = $this->extendedCan($perm, $member, $context);
        if ($extended !== null) {
            return $extended;
        }

        // Default permissions
        return (bool)Permission::checkMember($member, ChangeSet::config()->get('required_permission'));
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->ObjectHash = static::hashForSnapshot($this->ObjectClass, $this->ObjectID);
    }

    /**
     * @return DataObject
     */
    public function getItem()
    {
        return Versioned::get_version($this->ObjectClass, $this->ObjectID, $this->Version);
    }

    public function getItemTitle()
    {
        return $this->getItem()->singular_name() . '    --  ' . $this->getItem()->getTitle();
    }
}
