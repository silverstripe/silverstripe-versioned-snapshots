<?php

namespace SilverStripe\Snapshots;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\Versioned;
use Exception;

/**
 * Class SnapshotItem
 *
 * @property int $Version
 * @property int $WasPublished
 * @property int $WasDraft
 * @property int $WasDeleted
 * @property string $ObjectHash
 * @property int $SnapshotID
 * @property int $ObjectID
 * @property string $ObjectClass
 * @method Snapshot Snapshot()
 * @method DataObject Object()
 * @package SilverStripe\Snapshots
 */
class SnapshotItem extends DataObject
{

    use SnapshotHasher;

    /**
     * @var array
     */
    private static $db = [
        'Version' => 'Int',
        'WasPublished' => 'Boolean',
        'WasDraft' => 'Boolean',
        'WasDeleted' => 'Boolean',
        'ObjectHash' => 'Varchar(64)',
        'Modification' => 'Boolean', // indicates the snapshot item changes data (default true)
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Snapshot' => Snapshot::class,
        'Object' => DataObject::class,
        'Parent' => SnapshotItem::class,
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Version' => true,
        'ObjectHash' => true,
        'Object' => [
            'type' => 'unique',
            'columns' => ['ObjectHash', 'Version', 'SnapshotID']
        ]
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Modification' => true,
    ];

    /**
     * @var string
     */
    private static $table_name = 'VersionedSnapshotItem';

    /**
     * @var string
     */
    private static $singular_name = 'SnapshotItem';

    /**
     * @var string
     */
    private static $plural_name = 'SnapshotItems';

    /**
     * @var string
     */
    private static $default_sort = 'ID ASC';

    /**
     * @param null $member
     * @return bool
     */
    public function canView($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return $this->can(__FUNCTION__, $member, $context);
    }

    /**
     * @param null $member
     * @return bool
     */
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
     * Defaults to the tagged version for the snapshot item unless we're given a specific version
     * This was added to deal with a case in @see ActivityEntry::createFromSnapshotItem()
     *
     * @param int|null $version
     * @return DataObject|null
     */
    public function getItem(?int $version = null): ?DataObject
    {
        $version = $version ?? $this->Version;

        return Versioned::get_version($this->ObjectClass, $this->ObjectID, $version);
    }

    /**
     * @return string
     */
    public function getItemTitle()
    {
        return $this->getItem()->singular_name() . '    --  ' . $this->getItem()->getTitle();
    }

    /**
     * @param DataObject|Versioned|SnapshotPublishable $object
     * @return SnapshotItem
     * @throws Exception
     */
    public function hydrateFromDataObject(DataObject $object)
    {
        $this->ObjectClass = $object->baseClass();
        $this->ObjectID = (int) $object->ID;

        // Track versioning changes on the record if the owner is versioned
        if ($object->hasExtension(Versioned::class)) {
            $this->WasDraft = $object->isModifiedOnDraft();
            $this->WasDeleted = $object->isOnLiveOnly() || $object->isArchived();
            $this->Version = $object->Version;
        } else {
            // Track publish state for non-versioned owners, they're always in a published state.
            $this->WasPublished = true;
        }

        return $this;
    }
}
