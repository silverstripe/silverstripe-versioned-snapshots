<?php

namespace SilverStripe\Snapshots;

use Exception;
use InvalidArgumentException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * Class SnapshotPublishable
 *
 * @property DataObject|SnapshotPublishable|Versioned $owner
 */
class SnapshotPublishable extends RecursivePublishable
{

    use SnapshotHasher;

    /**
     * @var array
     * @config
     */
    private static $snapshot_relation_tracking = [];

    /**
     * @var array
     */
    private static $relationDiffs = [];

    /**
     * A more resilient wrapper for the Versioned function that holds up against un-staged versioned
     * implementations
     *
     * @param string $class
     * @param int $id
     * @return int|null
     */
    public static function get_published_version_number(string $class, int $id): ?int
    {
        $inst = DataObject::singleton($class);

        if (!$inst->hasExtension(Versioned::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s does not have the %s extension',
                $class,
                Versioned::class
            ));
        }

        /* @var Versioned|DataObject $inst */
        $stage = $inst->hasStages() ? Versioned::LIVE : Versioned::DRAFT;

        return Versioned::get_versionnumber_by_stage($class, $stage, $id);
    }

    /**
     * @param string $class
     * @param int $id
     * @param string|int $snapshot A snapshot ID or a Y-m-d h:i:s date formatted string
     * @return DataObject|null
     */
    public static function get_at_snapshot(string $class, int $id, $snapshot): ?DataObject
    {
        $baseClass = DataObject::getSchema()->baseDataClass($class);

        if (is_numeric($snapshot)) {
            $record = DataObject::get_by_id(Snapshot::class, $snapshot);

            if (!$record) {
                throw new InvalidSnapshotException($snapshot);
            }

            $snapshotDate = $record->Created;
        } else {
            $snapshotDate = $snapshot;
        }

        $list = DataList::create($baseClass)
            ->setDataQueryParam([
                'Versioned.mode' => 'archive',
                'Versioned.date' => $snapshotDate,
                'Versioned.stage' => Versioned::DRAFT,
            ]);

        return $list->byID($id);
    }

    public static function get_at_last_snapshot(string $class, int $id): ?DataObject
    {
        /** @var SnapshotItem $lastItem */
        $lastItem = static::get_last_snapshot_item($class, $id);

        if (!$lastItem) {
            return null;
        }

        return static::get_at_snapshot($class, $id, $lastItem->SnapshotID);
    }

    /**
     * @param string $class
     * @param int $id
     * @return DataObject|null
     */
    public static function get_last_snapshot_item(string $class, int $id): ?DataObject
    {
        return SnapshotItem::get()
            ->filter([
                'ObjectHash' => static::hashForSnapshot($class, $id)
            ])
            ->sort('Created', 'DESC')
            ->first();
    }

    /**
     * @return DataList
     */
    public static function getSnapshots(): DataList
    {
        return Snapshot::get()
            ->filter([
                // Avoid snapshots with no items
                'Items.ID:Not' => null,
            ]);
    }

    /**
     * @return DataList
     */
    public function getRelevantSnapshots(): DataList
    {
        $snapshots = $this->owner
            ->getSnapshots()
            ->filter([
                'Items.ObjectHash' => static::hashObjectForSnapshot($this->owner),
            ]);

        $this->owner->extend('updateRelevantSnapshots', $snapshots);

        return $snapshots;
    }

    /**
     * @param int|null $sinceVersion
     * @return DataList
     */
    public function getSnapshotsSinceVersion(?int $sinceVersion): DataList
    {
        $sinceVersion = (int) $sinceVersion;
        $lastPublishedSnapshotID = (int) SnapshotItem::get()
            ->filter([
                'ObjectHash' => static::hashObjectForSnapshot($this->owner),
                'Version' => $sinceVersion,
                'WasPublished' => 1,
            ])
            ->max('SnapshotID');

        return $this->owner
            ->getRelevantSnapshots()
            ->filter([
                // last published version
                'Items.Version:GreaterThanOrEqual' => $sinceVersion,
                // is not a snapshot of the last publishing
                'ID:GreaterThan' => $lastPublishedSnapshotID,
            ]);
    }

    /**
     * @return DataList
     */
    public function getSnapshotsSinceLastPublish(): DataList
    {
        $class = $this->owner->baseClass();
        $id = $this->owner->ID;
        $publishedVersion = static::get_published_version_number($class, $id);

        return $this->owner->getSnapshotsSinceVersion($publishedVersion);
    }

    /**
     * Get Snapshot item ids for snapshots between 2 versions
     * If $max is null, includes everything unpublished too
     *
     * @param int $min Minimal version to start looking with (inclusive)
     * @param int|null $max Maximal version to look until (inclusive)
     * @param bool $includeAll Include snapshot items that have no modifications
     *
     * @return array list snapshot item IDs
     */
    protected function getSnapshotsBetweenVersionsItemIds(int $min, ?int $max = null, bool $includeAll = false): array
    {
        $hash = static::hashObjectForSnapshot($this->owner);
        $minSnapshotID = (int) SnapshotItem::get()
            ->filter([
                'ObjectHash' => $hash,
                'Version' => $min,
            ])
            ->min('SnapshotID');

        $maxSnapshotID = (int) SnapshotItem::get()
            ->filter([
                'ObjectHash' => $hash,
                'Version' => $max,
            ])
            ->max('SnapshotID');

        $filters = [
            'ObjectHash' => $hash,
            'SnapshotID:GreaterThanOrEqual' => $minSnapshotID,
        ];

        if ($max) {
            $filters['SnapshotID:LessThanOrEqual'] = $maxSnapshotID;
        }

        if (!$includeAll) {
            $filters['Modification'] = 1;
        }

        return SnapshotItem::get()
            ->filter($filters)
            ->filterAny([
                'Version:Not' => $min,
                'WasPublished' => 0,
            ])
            ->column('ID');
    }

    /**
     * @return boolean
     */
    public function hasOwnedModifications(): bool
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return false;
        }

        $class = $this->owner->baseClass();
        $id = $this->owner->ID;
        $minVersion = static::get_published_version_number($class, $id);

        if (is_null($minVersion)) {
            // Draft page
            return false;
        }

        $ids = $this->getSnapshotsBetweenVersionsItemIds($minVersion);

        if (count($ids) === 0) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getPublishableItemsCount(): int
    {
        $snapShotIDs = $this->getSnapshotsSinceLastPublish()->column('ID');

        if (count($snapShotIDs) === 0) {
            return 0;
        }

        return $this->publishableItemsList($snapShotIDs)->count();
    }

    /**
     * @return ArrayList
     * @throws Exception
     */
    public function getPublishableObjects(): ArrayList
    {
        $snapShotIDs = $this->getSnapshotsSinceLastPublish()->column('ID');

        if (count($snapShotIDs) === 0) {
            return ArrayList::create();
        }

        $items = $this->publishableItemsList($snapShotIDs);
        $map = [];

        /** @var SnapshotItem $item */
        foreach ($items as $item) {
            $class = $item->ObjectClass;
            $id = $item->ObjectID;
            /* @var DataObject|SnapshotPublishable $obj */
            $obj = DataObject::get_by_id($class, $id);
            $map[static::hashObjectForSnapshot($obj)] = $obj;
        }

        return ArrayList::create(array_values($map));
    }

    /**
     * @return array
     */
    public function getRelationTracking(): array
    {
        $owner = $this->owner;
        $tracking = $owner->config()->get('snapshot_relation_tracking') ?? [];
        $data = [];

        foreach ($tracking as $relation) {
            if ($owner->hasMethod($relation) && $owner->getRelationClass($relation)) {
                $data[$relation] = $owner->$relation()->map('ID', 'Version')->toArray();
            }
        }

        return $data;
    }


    /**
     * @param int|string $snapshot A snapshot ID or  date formatted string
     * @return DataObject|null
     */
    public function getAtSnapshot($snapshot): ?DataObject
    {
        return static::get_at_snapshot($this->owner->baseClass(), $this->owner->ID, $snapshot);
    }

    /**
     * @return DataObject|null
     */
    public function getAtLastSnapshot(): ?DataObject
    {
        $lastItem = $this->owner->getPreviousSnapshotItem();

        if (!$lastItem) {
            return null;
        }

        return static::get_at_snapshot($this->owner->baseClass(), $this->owner->ID, $lastItem->SnapshotID);
    }

    /**
     * Tidy up all the irrelevant snapshot records now that the changes have been reverted.
     */
    public function onAfterRevertToLive(): void
    {
        $snapshots = $this->getSnapshotsSinceVersion($this->owner->Version)
            ->filter([
                'OriginHash' => static::hashObjectForSnapshot($this->owner),
            ]);

        $snapshots->removeAll();
    }

    /**
     * @return DataObject|null
     */
    public function getPreviousSnapshotItem(): ?DataObject
    {
        return SnapshotItem::get()->filter([
            'ObjectHash' => static::hashObjectForSnapshot($this->owner),
        ])
            ->sort('Created', 'DESC')
            ->first();
    }

    /**
     * @param callable $callback
     * @return mixed
     */
    public function atPreviousSnapshot(callable $callback)
    {
        // Get the last time this record was in a snapshot. Doesn't matter where or why. We just need a
        // timestamp prior to now, because the Version may be unchanged.
        $lastSnapshot = SnapshotItem::get()
            ->filter([
                'ObjectHash' => static::hashObjectForSnapshot($this->owner),
            ])
            ->max('LastEdited');

        return Versioned::withVersionedMode(function () use ($callback, $lastSnapshot) {
            Versioned::reading_archived_date($lastSnapshot);

            return $callback($lastSnapshot);
        });
    }

    /**
     * @return DataObject|null
     */
    public function getPreviousSnapshotVersion(): ?DataObject
    {
        return $this->atPreviousSnapshot(function ($date) {
            if (!$date) {
                return null;
            }

            return DataList::create($this->owner->baseClass())->byID($this->owner->ID);
        });
    }

    /**
     * @return bool
     */
    public function isModifiedSinceLastSnapshot(): bool
    {
        /** @var DataObject|Versioned $previous */
        $previous = $this->getPreviousSnapshotVersion();

        return !$previous || $previous->Version < $this->owner->Version;
    }

    /**
     * @return RelationDiffer[]
     * @todo Memorise / cache
     */
    public function getRelationDiffs(): array
    {
        $diffs = [];
        $previousTracking = $this->owner->atPreviousSnapshot(function ($date) {
            if (!$date) {
                return [];
            }

            /** @var DataObject|SnapshotPublishable $record */
            $record = DataObject::get_by_id($this->owner->baseClass(), $this->owner->ID, false);

            if (!$record) {
                return [];
            }

            return $record->getRelationTracking();
        });

        $currentTracking = $this->owner->getRelationTracking();

        foreach ($currentTracking as $relationName => $currentMap) {
            $class = $this->owner->getRelationClass($relationName);
            $type = $this->owner->getRelationType($relationName);
            $prevMap = $previousTracking[$relationName] ?? [];
            $diffs[] = RelationDiffer::create($class, $type, $prevMap, $currentMap);
        }

        static::$relationDiffs[static::hashObjectForSnapshot($this->owner)] = $diffs;

        return $diffs;
    }

    /**
     * @param bool $cache
     * @return bool
     */
    public function hasRelationChanges(bool $cache = true): bool
    {
        foreach ($this->getRelationDiffs($cache) as $diff) {
            if ($diff->hasChanges()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null $version
     * @return DataObject|null
     */
    public function getPreviousVersion($version = null): ?DataObject
    {
        $record = $this->owner;

        if ($record->Version == 1) {
            $previous = Injector::inst()->create(get_class($record));
        } else {
            if ($version === null) {
                $version = $record->Version - 1;
            }

            $previous = $record->getAtVersion($version);
        }

        return $previous;
    }

    /**
     * @param array $snapShotIDs
     * @return DataList
     * @throws Exception
     */
    protected function publishableItemsList(array $snapShotIDs): DataList
    {
        return SnapshotItem::get()
            ->filter([
                'Snapshot.ID' => $snapShotIDs,
                'WasPublished' => 0,
                'WasDeleted' => 0,
            ])
            ->applyRelation('Snapshot.OriginHash', $snapshotOriginHashColumn)
            ->whereAny([
                sprintf('"ObjectHash" = %s', $snapshotOriginHashColumn),
                '"ParentID" != 0'
            ])
            ->alterDataQuery(static function (DataQuery $dataQuery): void {
                $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
                $dataQuery->groupby([
                    '"ObjectHash"',
                    sprintf('%s."Created"', $itemTable),
                    sprintf('%s."ID"', $itemTable),
                ]);
            })
            ->sort([
                'Created' => 'ASC',
                'ID' => 'ASC',
            ]);
    }

    /**
     * @param DataObject $previous
     * @return array
     */
    protected function getChangedOwnership(DataObject $previous): array
    {
        $owner = $this->owner;

        // Build map of owned has-one relations
        $map = [];
        $lookup = $this->lookupReverseOwners();

        if (!isset($lookup[get_class($owner)])) {
            return [];
        }

        $hasOneLookup = array_flip($owner->hasOne());

        foreach ($lookup[get_class($owner)] as $info) {
            if (isset($hasOneLookup[$info['class']])) {
                $map[$hasOneLookup[$info['class']] . 'ID'] = $info['class'];
            }
        }

        $result = [];

        foreach ($map as $field => $class) {
            $previousValue = (int) $previous->$field;
            $currentValue = (int) $owner->$field;
            if ($previousValue === $currentValue) {
                continue;
            }

            $class = $map[$field];

            if (!$previousOwner = DataObject::get_by_id($class, $previousValue)) {
                continue;
            }

            if (!$currentOwner = DataObject::get_by_id($class, $currentValue)) {
                continue;
            }

            $result[] = [
                'previous' => $previousOwner,
                'current' => $currentOwner,
            ];
        }

        return $result;
    }

    /**
     * If ownership has changed, relocate the activity to the new owner.
     * There is no point to showing the old owner as "modified" since there
     * is nothing the user can do about it. Recursive publishing the old owner
     * will not affect this record, as it is no longer in its ownership graph.
     *
     * @throws Exception
     */
    public function reconcileOwnershipChanges(?DataObject $previous = null): void
    {
        if (!$previous) {
            return;
        }

        $changes = $this->getChangedOwnership($previous);

        foreach ($changes as $spec) {
            /* @var DataObject|SnapshotPublishable|Versioned $previousOwner */
            $previousOwner = $spec['previous'];
            $previousOwners = array_merge([$previousOwner], $previousOwner->findOwners()->toArray());

            /* @var DataObject|SnapshotPublishable|Versioned $currentOwner */
            $currentOwner = $spec['current'];
            $currentOwners = array_merge([$currentOwner], $currentOwner->findOwners()->toArray());

            $previousHashes = array_map([static::class, 'hashObjectForSnapshot'], $previousOwners);

            // Get the earliest snapshot where the previous owner was published.
            $cutoff = $previousOwner->getSnapshotsSinceLastPublish()
                ->sort('"ID" ASC')
                ->first();
            if (!$cutoff) {
                return;
            }

            // Get all the snapshots of the moved node since the cutoff.
            $snapshotsToMigrate = $this->owner->getSnapshotsSinceLastPublish()
                ->filter([
                    'ID:GreaterThanOrEqual' => $cutoff->ID,
                ]);

            // Todo: bulk update, optimise
            foreach ($snapshotsToMigrate as $snapshot) {
                $itemsToDelete = SnapshotItem::get()
                    ->filter([
                        'ObjectHash' => $previousHashes,
                        'SnapshotID' => $snapshot->ID,
                    ]);
                if ($itemsToDelete->exists()) {
                    // Rip out the old owners
                    $itemsToDelete->removeAll();

                    // Replace them with the new owners
                    /* @var DataObject|SnapshotPublishable $owner */
                    foreach ($currentOwners as $owner) {
                        $item = SnapshotItem::create();
                        $item->hydrateFromDataObject($owner);
                        $item->SnapshotID = $snapshot->ID;
                        $item->write();
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getIntermediaryObjects(): array
    {
        /* @var SnapshotPublishable|Versioned|DataObject $record */
        $record = $this->owner;

        if (!$record->hasExtension(static::class)) {
            return [];
        }

        $intermediaryObjects = $record->findOwners();
        $extraObjects = [];

        foreach ($intermediaryObjects as $extra) {
            $extraObjects[SnapshotHasher::hashObjectForSnapshot($extra)] = $extra;
        }

        return $extraObjects;
    }

    /**
     * @param int $min
     * @param null $max
     * @return DataList
     */
    public function getActivityBetweenVersions(int $min, $max = null): DataList
    {
        return SnapshotItem::get()
            ->byIDs($this->getSnapshotsBetweenVersionsItemIds($min, $max))
            ->filter([
                'Snapshot.ID:Not' => null,
            ])
            ->applyRelation('Snapshot.OriginHash', $snapshotOriginHashColumn)
            ->applyRelation('Children.ID', $childIdColumn)
            ->where([
                // Only get the items that were the subject of a user's action
                sprintf(
                    '(%1$s = "ObjectHash" AND %2$s IS NULL) OR (%1$s != "ObjectHash" AND "ParentID" != 0)',
                    $snapshotOriginHashColumn,
                    $childIdColumn
                ),
            ])
            ->sort([
                'SnapshotID' => 'ASC',
                'ID' => 'ASC',
            ]);
    }

    /**
     * Returns a list of ActivityEntry ordered by creation datetime
     *
     * @param int|null $minVersion version to start with (or last published if null)
     * @param int|null $maxVersion version to end with (or till the end, including everything unpublished)
     *
     * @return ArrayList list of ActivityEntry
     * @throws Exception
     */
    public function getActivityFeed(?int $minVersion = null, ?int $maxVersion = null): ArrayList
    {
        if (is_null($minVersion)) {
            $class = $this->owner->baseClass();
            $id = $this->owner->ID;
            $minVersion = static::get_published_version_number($class, $id);

            if (is_null($minVersion)) {
                $minVersion = 1;
            }
        }

        $items = $this->getActivityBetweenVersions($minVersion, $maxVersion);
        $list = ArrayList::create();

        foreach ($items as $item) {
            $list->push(ActivityEntry::createFromSnapshotItem($item));
        }

        return $list;
    }
}
