<?php

namespace SilverStripe\Snapshots;

use Exception;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\Versioned;

/**
 * @property int $ObjectVersion
 * @property int $WasPublished
 * @property int $WasUnpublished
 * @property int $WasCreated
 * @property int $WasDraft
 * @property int $WasDeleted
 * @property string $ObjectHash
 * @property int $SnapshotID
 * @property int $ObjectID
 * @property string $ObjectClass
 * @property string $Modification
 * @method Snapshot Snapshot()
 * @method DataObject Object()
 * @method SnapshotItem Parent()
 * @method HasManyList|SnapshotItem[] Children()
 */
class SnapshotItem extends DataObject
{

    use SnapshotHasher;

    /**
     * @var array
     */
    private static $db = [
        'ObjectVersion' => 'Int',
        'WasPublished' => 'Boolean',
        'WasDraft' => 'Boolean',
        'WasDeleted' => 'Boolean',
        'WasUnpublished' => 'Boolean',
        'WasCreated' => 'Boolean',
        'ObjectHash' => 'Varchar(64)',
        'Modification' => 'Boolean',
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
    private static $has_many = [
        'Children' => SnapshotItem::class,
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'ObjectVersion' => true,
        'ObjectHash' => true,
        'Object' => [
            'columns' => ['ObjectHash', 'SnapshotID'],
        ],
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
        return (bool) Permission::checkMember($member, ChangeSet::config()->get('required_permission'));
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->ObjectHash = $this->hashForSnapshot($this->ObjectClass, $this->ObjectID);
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
        $singleton = DataObject::singleton($this->ObjectClass);

        // Item is versioned - find the requested version
        if ($singleton->hasExtension(Versioned::class)) {
            $version = $version ?? $this->ObjectVersion;

            return Versioned::get_all_versions($this->ObjectClass, $this->ObjectID)
                ->find('Version', $version);
        }

        // Item is not versioned - return it as it is
        return DataObject::get_by_id($this->ObjectClass, $this->ObjectID);
    }

    /**
     * @return string
     */
    public function getItemTitle(): string
    {
        return $this->getItem()->singular_name() . '    --  ' . $this->getItem()->getTitle();
    }

    /**
     * @param DataObject|Versioned|SnapshotPublishable $object
     * @return SnapshotItem
     * @throws Exception
     */
    public function hydrateFromDataObject(DataObject $object): self
    {
        $objectID = (int) ($object->ID ?: $object->OldID);

        $this->ObjectClass = $object->baseClass();
        $this->ObjectID = $objectID;
        $this->WasUnpublished = false;

        // Track versioning changes on the record if the owner is versioned
        if ($object->hasExtension(Versioned::class)) {
            $numVersions = Versioned::get_all_versions($object->baseClass(), $objectID)
                ->count();
            $this->WasCreated = $numVersions == 1;
            $this->WasPublished = false;
            $this->WasDraft = $object->isModifiedOnDraft();
            $this->WasDeleted = $object->isOnLiveOnly() || $object->isArchived();
            $this->ObjectVersion = $object->Version;
        } else {
            // Track publish state for non-versioned owners, they're always in a published state.
            $exists = SnapshotItem::get()->filter([
                'ObjectHash' => $this->hashObjectForSnapshot($object),
            ]);
            $this->WasCreated = !$exists->exists();
            $this->WasPublished = true;
            $this->WasDraft = false;
            $this->WasDeleted = false;
        }

        $object->invokeWithExtensions('updateHydrateFromObject', $this);

        return $this;
    }
}
