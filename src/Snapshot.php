<?php

namespace SilverStripe\Snapshots;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use Exception;

/**
 * Class Snapshot
 *
 * @property string $OriginHash
 * @property string $Message
 * @property int $OriginID
 * @property string $OriginClass
 * @property int AuthorID
 * @method DataObject Origin()
 * @method Member Author()
 * @method HasManyList|SnapshotItem[] Items()
 * @package SilverStripe\Snapshots
 */
class Snapshot extends DataObject
{

    use SnapshotHasher;

    /**
     * @var array
     */
    private static $db = [
        'OriginHash' => 'Varchar(64)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Origin' => DataObject::class,
        'Author' => Member::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Items' => SnapshotItem::class,
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'OriginHash' => true,
    ];

    /**
     * @var string
     */
    private static $table_name = 'VersionedSnapshot';

    /**
     * @var string
     */
    private static $singular_name = 'Snapshot';

    /**
     * @var string
     */
    private static $plural_name = 'Snapshots';

    /**
     * @var string
     */
    private static $default_sort = 'ID ASC';

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Items',
    ];

    /**
     * @var int Limit the number of snapshot items, for performance reasons
     * @config
     */
    private static $item_limit = 20;

    /**
     * @return SnapshotItem|null
     */
    public function getOriginItem()
    {
        return $this->Items()->filter([
            'ObjectHash' => $this->OriginHash,
        ])->first();
    }

    /**
     * Shortcut for adding items by their associated dataobjects
     * @param DataObject $obj
     * @return $this
     * @throws Exception
     */
    public function addObject(DataObject $obj): self
    {
        if ($this->Items()->count() >= $this->config()->item_limit) {
            return $this;
        }

        if ($obj instanceof SnapshotItem) {
            foreach ($this->Items() as $item) {
                if ($item->ObjectClass === $obj->ObjectClass && $item->ObjectID === $obj->ObjectID) {
                    return $this;
                }
            }
            $this->Items()->add($obj);
        } else {
            // Ensure uniqueness
            foreach ($this->Items() as $item) {
                if ($item->ObjectClass === $obj->baseClass() && $item->ObjectID === $obj->ID) {
                    return $this;
                }
            }
            $item = SnapshotItem::create()
                ->hydrateFromDataObject($obj);

            $this->Items()->add($item);
        }

        return $this;
    }

    /**
     * @return SnapshotItem|null
     */
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

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->LastEdited;
    }

    /**
     * @return string
     */
    public function getActivityDescription(): string
    {
        $item = $this->getOriginItem();
        return $item
            ? ActivityEntry::createFromSnapshotItem($item)->getDescription()
            : _t(__CLASS__ . 'ACTIVITY_NONE', 'none');
    }

    public function getActivityAgo(): string
    {
        return $this->obj('Created')->Ago(false);
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function getActivityType(): ?string
    {
        $item = $this->getOriginItem();
        return $item
            ? ActivityEntry::createFromSnapshotItem($item)->Action
            : null;
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool|int
     */
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool|int
     */
    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool|int
     */
    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        return true;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->OriginHash = static::hashForSnapshot($this->OriginClass, $this->OriginID);
    }

    /**
     *
     * @param DataObject|null $origin
     * @param array $extraObjects
     * @return Snapshot|null
     * @throws ValidationException
     */
    public function createSnapshot(
        DataObject $origin,
        array $extraObjects = [],
        $cache = true
    ): ?Snapshot {
        if (!$origin->hasExtension(SnapshotPublishable::class)) {
            return null;
        }

        $currentUser = Security::getCurrentUser();
        $snapshot = Snapshot::create();
        $snapshot->AuthorID = $currentUser
            ? (int) $currentUser->ID
            : 0;
        $snapshot->applyOrigin($origin);
        $snapshot->addOwnershipChain($origin);

        if ($origin->hasRelationChanges($cache)) {
            // Change of course. This snapshot is about an update to a relationship (e.g. many_many)
            // and not really about the provided "origin".
            $diffs = $origin->getRelationDiffs($cache);
            $event = ImplicitModification::create()
                ->hydrateFromDiffs($diffs);
            $event->write();
            $snapshot->applyOrigin($event);
            $eventItem = $snapshot->getOriginItem();
            /* @var RelationDiffer $diff */
            foreach ($diffs as $diff) {
                foreach ($diff->getRecords() as $obj) {
                    $item = SnapshotItem::create()
                        ->hydrateFromDataObject($obj);
                    if ($diff->isRemoved($obj->ID)) {
                        $item->WasDeleted = true;
                    }
                    $eventItem->Children()->add($item);
                    $snapshot->addObject($item);
                }
            }
        }

        foreach ($extraObjects as $o) {
            $snapshot->addObject($o);
        }

        $origin->reconcileOwnershipChanges($origin->getPreviousVersion());

        return $snapshot;
    }

    /**
     * @param string $message
     * @param array $extraObjects
     * @return Snapshot
     * @throws ValidationException
     */
    public function createSnapshotEvent(string $message, $extraObjects = []): Snapshot
    {
        $event = SnapshotEvent::create([
            'Title' => $message,
        ]);
        $event->write();

        return $this->createSnapshot($event, $extraObjects);
    }

    /**
     * sets the related snapshot items to not modified
     *
     * items with modifications are used to determine the owner's modification
     * status (eg in site tree's status flags)
     */
    public function markNoModifications(): void
    {
        foreach ($this->Items() as $item) {
            $item->Modification = false;
            $item->write();
        }
    }

    /**
     * @param DataObject $origin
     * @return $this
     * @throws Exception
     */
    public function applyOrigin(DataObject $origin): self
    {
        $this->OriginClass = $origin->baseClass();
        $this->OriginID = $origin->ID;
        $this->addObject($origin);

        return $this;
    }

    /**
     * @param DataObject $obj
     * @return $this
     * @throws Exception
     */
    public function addOwnershipChain(DataObject $obj): self
    {
        $this->addObject($obj);
        foreach ($obj->getIntermediaryObjects() as $o) {
            $this->addObject($o);
        }

        return $this;
    }
}
