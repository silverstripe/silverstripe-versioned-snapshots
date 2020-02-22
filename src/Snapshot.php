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
        // Ensure uniqueness
        foreach ($this->Items() as $item) {
            if ($item->ObjectClass === $obj->baseClass() && $item->ID === $obj->ID) {
                return $this;
            }
        }
        $item = SnapshotItem::create()
            ->hydrateFromDataObject($obj);

        $this->Items()->add($item);

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
        array $extraObjects = []
    ): ?Snapshot {
        if (!$origin->hasExtension(SnapshotPublishable::class)) {
            return null;
        }
        $objectsToAdd = [$origin];
        /* @var SnapshotPublishable $origin */
        $intermediaryObjects = $origin->getIntermediaryObjects();
        $implicitModifications = [];
        $messages = [];
        $diffs = $origin->getRelationDiffs();
        /* @var RelationDiffer $diff */
        foreach ($diffs as $diff) {
            $messages = array_merge($messages, $this->getMessagesForDiff($diff));
            $implicitModifications = array_merge($implicitModifications, $diff->getModifications());
        }
        if (!empty($implicitModifications)) {
            $origin = SnapshotEvent::create([
              'Title' => implode("\n", $messages),
            ]);
            $origin->write();
        }

        $implicitObjects = array_map(function (Modification $mod) {
                return $mod->getRecord();
        }, $implicitModifications);

        $objectsToAdd = array_merge($objectsToAdd, $intermediaryObjects, $implicitObjects);

        $currentUser = Security::getCurrentUser();
        $snapshot = Snapshot::create();

        $snapshot->OriginClass = $origin->baseClass();
        $snapshot->OriginID = (int) $origin->ID;
        $snapshot->AuthorID = $currentUser
            ? (int) $currentUser->ID
            : 0;

        $objects = array_merge($objectsToAdd, $extraObjects);
        // the rest of the objects are processed in the provided order
        foreach ($objects as $object) {
            if (!$object instanceof DataObject) {
                continue;
            }
            $snapshot->addObject($object);
        }

        if (!empty($implicitModifications)) {
            $snapshot->applyImplicitObjects($implicitModifications);
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
     * When implicit objects are updated (e.g. many_many), assign the ParentID
     *
     * @param Modification[] $implicitObjects
     * @throws ValidationException
     */
    public function applyImplicitObjects($implicitObjects = []): void
    {
        $parentItem = $this->getOriginItem();
        if (!$parentItem) {
            return;
        }

        foreach ($implicitObjects as $mod) {
            $obj = $mod->getRecord();
            $type = $mod->getActivityType();
            $item = $this->Items()->filter(
                'ObjectHash',
                SnapshotHasher::hashObjectForSnapshot($obj)
            )->first();
            if ($item) {
                $item->ParentID = $parentItem->ID;
                $item->WasDeleted = $type === ActivityEntry::REMOVED;
                $item->write();
            }
        }
    }

    private function getMessagesForDiff(RelationDiffer $diff): array
    {
        $relationType = $diff->getRelationType();
        $messages = [];
        $class = $diff->getRelationClass();
        $sng = Injector::inst()->get($class);
        $i18nGraph = [
            'added' => ['Added', 'Created'],
            'removed' => ['Removed', 'Deleted'],
            'changed' => ['Modified', 'Modified'],
        ];
        foreach ($i18nGraph as $category => $labels) {
            $getter = 'get' . ucfirst($category);
            // Number of records in 'added', or 'removed', etc.
            $ct = count($diff->$getter());
            // e.g. MANY_MANY, HAS_MANY
            $i18nRelationKey = strtoupper($relationType);
            // e.g. use "Added" for many_many, "Created" for has_many
            list ($manyManyLabel, $hasManyLabel) = $labels;
            $action = $relationType === 'many_many' ? $manyManyLabel : $hasManyLabel;
            // e.g. ADDED, for MANY_MANY_ADDED
            $i18nActionKey = strtoupper($action);

            // If singular, be specific with the record
            if ($ct === 1) {
                $record = DataObject::get_by_id($class, $diff->$getter()[0]);
                if ($record) {
                    $messages[] = _t(
                        __CLASS__ . '.' . $i18nRelationKey . '_' . $i18nActionKey . '_ONE',
                        $action . ' {type} "{title}"',
                        [
                            'type' => $sng->singular_name(),
                            'title' => $record->getTitle(),
                        ]
                    );
                }
                // Otherwise, just give a count
            } else if ($ct > 1) {
                $messages[] = _t(
                    __CLASS__ . '.' . $i18nRelationKey . '_' . $i18nActionKey . '_MANY',
                    $action . ' {count} {name}',
                    [
                        'count' => $ct,
                        'name' => $ct > 1 ? $sng->plural_name() : $sng->singular_name()
                    ]
                );
            }
        }

        return $messages;
    }
}
