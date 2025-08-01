<?php

namespace SilverStripe\Snapshots;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Snapshots\RelationDiffer\RelationDiffer;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $OriginHash
 * @property int $OriginID
 * @property string $OriginClass
 * @property int AuthorID
 * @method DataObject Origin()
 * @method Member Author()
 * @method HasManyList<SnapshotItem> Items()
 */
class Snapshot extends DataObject
{

    use SnapshotHasher;

    private static array $db = [
        'OriginHash' => 'Varchar(64)',
    ];

    private static array $has_one = [
        'Origin' => DataObject::class,
        'Author' => Member::class,
    ];

    private static array $has_many = [
        'Items' => SnapshotItem::class,
    ];

    private static array $indexes = [
        'OriginHash' => true,
    ];

    private static string $table_name = 'VersionedSnapshot';

    private static string $singular_name = 'Snapshot';

    private static string $plural_name = 'Snapshots';

    private static string $class_description = 'Represents event that spans multiple models';

    private static string $default_sort = 'ID ASC';

    private static array $cascade_deletes = [
        'Items',
    ];

    /**
     * @var int Limit the number of snapshot items, for performance reasons
     * @config
     */
    private static int $item_limit = 20;

    public function getOriginItem(): ?SnapshotItem
    {
        /** @var SnapshotItem $item */
        $item = $this
            ->Items()
            ->find('ObjectHash', $this->OriginHash);

        if ($item instanceof DataObject) {
            return $item;
        }

        return null;
    }

    /**
     * Shortcut for adding items by their associated data objects
     *
     * @param DataObject $obj
     * @return $this
     * @throws Exception
     */
    public function addObject(DataObject $obj): Snapshot
    {
        $items = $this->Items();

        if ($items->count() >= $this->config()->get('item_limit')) {
            return $this;
        }

        if ($obj instanceof SnapshotItem) {
            foreach ($items as $item) {
                if ($item->ObjectClass === $obj->ObjectClass && $item->ObjectID === $obj->ObjectID) {
                    return $this;
                }
            }

            $items->add($obj);
        } else {
            // Ensure uniqueness
            foreach ($items as $item) {
                if ($item->ObjectClass === $obj->baseClass() && $item->ObjectID === ($obj->ID ?: $obj->OldID)) {
                    return $this;
                }
            }

            $item = SnapshotItem::create()->hydrateFromDataObject($obj);
            $items->add($item);
        }

        return $this;
    }

    public function getOriginVersion(): ?DataObject
    {
        $originItem = $this->getOriginItem();

        if ($originItem) {
            return Versioned::get_version(
                $originItem->ObjectClass,
                $originItem->ObjectID,
                $originItem->ObjectVersion
            );
        }

        return null;
    }

    public function getDate(): string
    {
        return $this->LastEdited;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getActivityDescription(): string
    {
        /** @var SnapshotItem $item */
        $item = $this->getOriginItem();
        $entry = ActivityEntry::singleton()->createFromSnapshotItem($item);
        $key = sprintf('%s.ACTIVITY_NONE', Snapshot::class);
        $default = _t($key, 'none');

        return $entry
            ? $entry->getDescription()
            : $default;
    }

    public function getActivityAgo(): string
    {
        /** @var DBDatetime $lastEditedField */
        $lastEditedField = $this->obj('LastEdited');

        return $lastEditedField->Ago(false);
    }

    public function getIsLiveSnapshot(): bool
    {
        /** @var Versioned|DataObject $originVersion */
        $originVersion = $this->getOriginVersion();

        if (!$originVersion) {
            return false;
        }

        if ($originVersion->hasStages() && !$originVersion->isLiveVersion()) {
            return false;
        }

        $liveVersionNumber = SnapshotPublishable::singleton()->getPublishedVersionNumber(
            $originVersion->baseClass(),
            $originVersion->ID
        );

        $latestPublishID = SnapshotItem::get()
            ->filter([
                'ObjectVersion' => $liveVersionNumber,
                'ObjectHash' => $this->hashObjectForSnapshot($originVersion),
            ])
            ->max('SnapshotID');

        return $this->ID === $latestPublishID;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function getActivityType(): ?string
    {
        /** @var SnapshotItem $item */
        $item = $this->getOriginItem();
        $entry = ActivityEntry::singleton()->createFromSnapshotItem($item);

        return $entry?->Action;
    }

    /**
     * @param Member|null $member
     * @param array $context
     * @return bool
     */
    public function canCreate(mixed $member = null, mixed $context = []): bool
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param Member|null $member
     * @return bool
     */
    public function canEdit(mixed $member = null): bool
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param Member|null $member
     * @return bool
     */
    public function canDelete(mixed $member = null): bool
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param Member|null $member
     * @return bool
     */
    public function canView(mixed $member = null): bool
    {
        return true;
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->OriginHash = $this->hashForSnapshot($this->OriginClass, $this->OriginID);
    }

    /**
     * @param DataObject $origin
     * @param array $extraObjects
     * @return Snapshot|null
     * @throws ValidationException
     * @throws Exception
     * @throws NotFoundExceptionInterface
     */
    public function createSnapshot(
        DataObject $origin,
        array $extraObjects = []
    ): ?Snapshot {
        /** @var DataObject|SnapshotPublishable $origin */
        if (!$origin->hasExtension(SnapshotPublishable::class)) {
            return null;
        }

        if (!$origin->hasExtension(Versioned::class)) {
            // We require origin to be versioned, if it's not we can bail out
            return null;
        }

        $currentUser = Security::getCurrentUser();
        $snapshot = Snapshot::create();
        $snapshot->AuthorID = (int) $currentUser?->ID;
        $snapshot->applyOrigin($origin);
        $snapshot->addOwnershipChain($origin);

        if ($origin->hasRelationChanges()) {
            // Change of course. This snapshot is about an update to a relationship (e.g. many_many)
            // and not really about the provided "origin".
            $diffs = $origin->getRelationDiffs();
            $event = ImplicitModification::create()->hydrateFromDiffs($diffs);
            $event->write();
            $snapshot->applyOrigin($event);

            /** @var SnapshotItem $eventItem */
            $eventItem = $snapshot->getOriginItem();

            /** @var RelationDiffer $diff */
            foreach ($diffs as $diff) {
                foreach ($diff->getRecords() as $obj) {
                    $item = SnapshotItem::create()->hydrateFromDataObject($obj);

                    if ($diff->isRemoved($obj->ID ?: $obj->OldID)) {
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
     * @throws NotFoundExceptionInterface
     */
    public function createSnapshotEvent(string $message, array $extraObjects = []): Snapshot
    {
        $event = SnapshotEvent::create();
        $event->Title = $message;
        $event->write();

        return $this->createSnapshot($event, $extraObjects);
    }

    /**
     * sets the related snapshot items to not modified
     *
     * items with modifications are used to determine the owner's modification
     * status (e.g. in site tree's status flags)
     *
     * @throws ValidationException
     */
    public function markNoModifications(): void
    {
        /** @var SnapshotItem $item */
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
    public function applyOrigin(DataObject $origin): Snapshot
    {
        $this->OriginClass = $origin->baseClass();
        // Handler for deleted records
        $this->OriginID = $origin->ID ?: $origin->OldID;
        $this->addObject($origin);

        return $this;
    }

    /**
     * @param DataObject|SnapshotPublishable $model
     * @return Snapshot
     * @throws Exception
     */
    public function addOwnershipChain(DataObject $model): Snapshot
    {
        $this->addObject($model);

        foreach ($model->getIntermediaryObjects() as $object) {
            $this->addObject($object);
        }

        return $this;
    }
}
