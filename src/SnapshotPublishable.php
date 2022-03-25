<?php

namespace SilverStripe\Snapshots;

use Exception;
use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * Class SnapshotPublishable
 *
 * @property DataObject|SnapshotPublishable|Versioned $owner
 */
class SnapshotPublishable extends RecursivePublishable implements Resettable
{

    use Injectable;
    use SnapshotHasher;

    /**
     * @var array
     * @config
     */
    private static $snapshot_relation_tracking = [];

    /**
     * @var array
     */
    private $relationDiffs = [];

    public function flushCachedData(): void
    {
        $this->relationDiffs = [];
    }

    public static function reset(): void
    {
        static::singleton()->flushCachedData();
    }

    /**
     * A more resilient wrapper for the Versioned function that holds up against un-staged versioned
     * implementations
     *
     * @param string $class
     * @param int $id
     * @return int|null
     */
    public function getPublishedVersionNumber(string $class, int $id): ?int
    {
        $inst = DataObject::singleton($class);

        if (!$inst->hasExtension(Versioned::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s does not have the %s extension',
                $class,
                Versioned::class
            ));
        }

        /** @var Versioned|DataObject $inst */
        $stage = $inst->hasStages()
            ? Versioned::LIVE
            : Versioned::DRAFT;

        return Versioned::get_versionnumber_by_stage($class, $stage, $id);
    }

    /**
     * @param string $class
     * @param int $id
     * @param string|int $snapshot A snapshot ID or a Y-m-d h:i:s date formatted string
     * @return DataObject|null
     */
    public function getAtSnapshotByClassAndId(string $class, int $id, $snapshot): ?DataObject
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

    public function getAtLastSnapshotByClassAndId(string $class, int $id): ?DataObject
    {
        /** @var SnapshotItem $lastItem */
        $lastItem = $this->getLastSnapshotItemByClassAndId($class, $id);

        if (!$lastItem) {
            return null;
        }

        return $this->getAtSnapshotByClassAndId($class, $id, $lastItem->SnapshotID);
    }

    /**
     * @param string $class
     * @param int $id
     * @return DataObject|null
     */
    public function getLastSnapshotItemByClassAndId(string $class, int $id): ?DataObject
    {
        return SnapshotItem::get()
            ->sort('Created', 'DESC')
            ->find('ObjectHash', $this->hashForSnapshot($class, $id));
    }

    /**
     * @return DataList
     */
    public function getSnapshots(): DataList
    {
        return Snapshot::get()->filter([
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
                'Items.ObjectHash' => $this->hashObjectForSnapshot($this->owner),
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
                'ObjectHash' => $this->hashObjectForSnapshot($this->owner),
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
        $publishedVersion = $this->getPublishedVersionNumber($class, $id);

        return $this->owner->getSnapshotsSinceVersion($publishedVersion);
    }

    /**
     * Get Snapshot item ids for snapshots between 2 versions
     * If $max is null, includes everything unpublished too
     *
     * @param int $min Minimal version to start looking with (inclusive)
     * @param int|null $max Maximal version to look until (inclusive)
     * @param bool $includeAll Include snapshot items that have no modifications
     * @return DataList
     */
    protected function getSnapshotsBetweenVersionsItems(int $min, ?int $max = null, bool $includeAll = false): DataList
    {
        $hash = $this->hashObjectForSnapshot($this->owner);
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
            ]);
    }

    /**
     * @return bool
     */
    public function hasOwnedModifications(): bool
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return false;
        }

        $class = $this->owner->baseClass();
        $id = $this->owner->ID;
        $minVersion = $this->getPublishedVersionNumber($class, $id);

        if (is_null($minVersion)) {
            // Draft page
            return false;
        }

        $snapshotIds = $this->getSnapshotsBetweenVersionsItems($minVersion)
            ->column('SnapshotID');

        if (count($snapshotIds) === 0) {
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
        $snapshotIds = $this->getSnapshotsSinceLastPublish()->column('ID');

        if (count($snapshotIds) === 0) {
            return 0;
        }

        return $this->publishableItemsList($snapshotIds)->count();
    }

    /**
     * @return ArrayList
     * @throws Exception
     */
    public function getPublishableObjects(): ArrayList
    {
        $snapshotIds = $this->getSnapshotsSinceLastPublish()->column('ID');

        if (count($snapshotIds) === 0) {
            return ArrayList::create();
        }

        $items = $this->publishableItemsList($snapshotIds);
        $map = [];

        /** @var SnapshotItem $item */
        foreach ($items as $item) {
            $class = $item->ObjectClass;
            $id = $item->ObjectID;

            /** @var DataObject|SnapshotPublishable $obj */
            $obj = DataObject::get_by_id($class, $id);
            $map[$this->hashObjectForSnapshot($obj)] = $obj;
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
            if (!$owner->hasMethod($relation) || !$owner->getRelationClass($relation)) {
                continue;
            }

            $data[$relation] = $owner->$relation()->map('ID', 'Version')->toArray();
        }

        return $data;
    }


    /**
     * @param int|string $snapshot A snapshot ID or  date formatted string
     * @return DataObject|null
     */
    public function getAtSnapshot($snapshot): ?DataObject
    {
        return $this->getAtSnapshotByClassAndId($this->owner->baseClass(), $this->owner->ID, $snapshot);
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

        return $this->getAtSnapshotByClassAndId($this->owner->baseClass(), $this->owner->ID, $lastItem->SnapshotID);
    }

    /**
     * Tidy up all the irrelevant snapshot records now that the changes have been reverted.
     * Extension point in @see Versioned::doRevertToLive()
     */
    public function onAfterRevertToLive(): void
    {
        $snapshots = $this->getSnapshotsSinceVersion($this->owner->Version)
            ->filter([
                'OriginHash' => $this->hashObjectForSnapshot($this->owner),
            ]);

        $snapshots->removeAll();
    }

    /**
     * @return SnapshotItem|null
     */
    public function getPreviousSnapshotItem(): ?DataObject
    {
        return SnapshotItem::get()
            ->sort('Created', 'DESC')
            ->find('ObjectHash', $this->hashObjectForSnapshot($this->owner));
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
                'ObjectHash' => $this->hashObjectForSnapshot($this->owner),
            ])
            ->max('LastEdited');

        return Versioned::withVersionedMode(static function () use ($callback, $lastSnapshot) {
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
        /** @var SnapshotItem $previous */
        $previous = $this->getPreviousSnapshotVersion();

        return !$previous || $previous->Version < $this->owner->Version;
    }

    /**
     * @param bool $cache
     * @return RelationDiffer[]
     * @throws Exception
     * TODO Memoise / cache / enable cache once it's confirmed that this feature is needed
     */
    public function getRelationDiffs(bool $cache = true): array
    {
        $cacheKey = $this->owner->isInDB()
            ? sprintf(
                '%s-%s',
                $this->owner->getUniqueKey(),
                $this->hashObjectForSnapshot($this->owner)
            )
            : '';
        // TODO in-memory cache disabled until we can confirm that we need it
        $cacheKey = '';

        if ($cache && $cacheKey && array_key_exists($cacheKey, $this->relationDiffs)) {
            return $this->relationDiffs[$cacheKey];
        }

        $diffs = [];
        $previousTracking = $this->owner->atPreviousSnapshot(function ($date) {
            if (!$date) {
                return [];
            }

            $record = DataObject::get_by_id($this->owner->baseClass(), $this->owner->ID, false);

            if (!$record) {
                return [];
            }

            /** @var DataObject|SnapshotPublishable $record */
            return $record->getRelationTracking();
        });

        $currentTracking = $this->owner->getRelationTracking();

        foreach ($currentTracking as $relationName => $currentMap) {
            $class = $this->owner->getRelationClass($relationName);
            $type = $this->owner->getRelationType($relationName);
            $prevMap = $previousTracking[$relationName] ?? [];
            $diffs[] = RelationDiffer::create($class, $type, $prevMap, $currentMap);
        }

        if ($cacheKey) {
            $this->relationDiffs[$cacheKey] = $diffs;
        }

        return $diffs;
    }

    /**
     * @param bool $cache
     * @return bool
     * @throws Exception
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
            $previous = Injector::inst()->create($record->ClassName);
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
        $singleton = SnapshotItem::singleton();
        $itemTable = DataObject::getSchema()->tableName($singleton->ClassName);

        return SnapshotItem::get()
            ->filter([
                'Snapshot.ID' => $snapShotIDs,
                'WasPublished' => 0,
                'WasDeleted' => 0,
            ])
            ->applyRelation('Snapshot.OriginHash', $snapshotOriginHashColumn)
            ->whereAny([
                sprintf('"%s"."ObjectHash" = %s', $itemTable, $snapshotOriginHashColumn),
                sprintf('"%s"."ParentID" != 0', $itemTable),
            ])
            ->alterDataQuery(static function (DataQuery $dataQuery) use ($itemTable): void {
                $dataQuery->groupby([
                    sprintf('"%s"."ObjectHash"', $itemTable),
                    sprintf('"%s"."Created"', $itemTable),
                    sprintf('"%s"."ID"', $itemTable),
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
            if (!isset($hasOneLookup[$info['class']])) {
                continue;
            }

            $map[$hasOneLookup[$info['class']] . 'ID'] = $info['class'];
        }

        $result = [];

        foreach ($map as $field => $class) {
            $previousValue = (int) $previous->{$field};
            $currentValue = (int) $owner->{$field};

            if ($previousValue === $currentValue) {
                continue;
            }

            $class = $map[$field];
            $previousOwner = DataObject::get_by_id($class, $previousValue);

            if (!$previousOwner) {
                continue;
            }

            $currentOwner = DataObject::get_by_id($class, $currentValue);

            if (!$currentOwner) {
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
            /** @var DataObject|SnapshotPublishable|Versioned $previousOwner */
            $previousOwner = $spec['previous'];
            $previousOwners = array_merge([$previousOwner], $previousOwner->findOwners()->toArray());

            /** @var DataObject|SnapshotPublishable|Versioned $currentOwner */
            $currentOwner = $spec['current'];
            $currentOwners = array_merge([$currentOwner], $currentOwner->findOwners()->toArray());

            $previousHashes = array_map([$this, 'hashObjectForSnapshot'], $previousOwners);

            // Get the earliest snapshot where the previous owner was published.
            $cutoff = $previousOwner->getSnapshotsSinceLastPublish()
                ->sort('ID', 'ASC')
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

                if (!$itemsToDelete->exists()) {
                    continue;
                }

                // Rip out the old owners
                $itemsToDelete->removeAll();

                /** @var DataObject|SnapshotPublishable $owner */
                foreach ($currentOwners as $owner) {
                    // Replace them with the new owners
                    $item = SnapshotItem::create();
                    $item->hydrateFromDataObject($owner);
                    $item->SnapshotID = $snapshot->ID;
                    $item->write();
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getIntermediaryObjects(): array
    {
        /** @var SnapshotPublishable|Versioned|DataObject $record */
        $record = $this->owner;

        if (!$record->hasExtension(static::class)) {
            return [];
        }

        $intermediaryObjects = $record->findOwners();
        $extraObjects = [];

        foreach ($intermediaryObjects as $extra) {
            $extraObjects[$this->hashObjectForSnapshot($extra)] = $extra;
        }

        return $extraObjects;
    }

    /**
     * @param int $min
     * @param int|null $max
     * @return SS_List
     */
    public function getActivityBetweenVersions(int $min, ?int $max = null): SS_List
    {
        $singleton = SnapshotItem::singleton();
        $snapshotIds = $this->getSnapshotsBetweenVersionsItems($min, $max)
            ->column('SnapshotID');

        if (count($snapshotIds) === 0) {
            return ArrayList::create()->setDataClass($singleton->ClassName);
        }

        $itemTable = DataObject::getSchema()->tableName($singleton->ClassName);

        return SnapshotItem::get()
            ->filter([
                // Intentionally forcing a join here
                'Snapshot.ID' => $snapshotIds,
            ])
            ->applyRelation('Snapshot.OriginHash', $snapshotOriginHashColumn)
            ->applyRelation('Children.ID', $childIdColumn)
            ->where([
                // Only get the items that were the subject of a user's action
                sprintf(
                    '(%1$s = "%3$s"."ObjectHash" AND %2$s IS NULL) OR '
                    . '(%1$s != "%3$s"."ObjectHash" AND "%3$s"."ParentID" != 0)',
                    $snapshotOriginHashColumn,
                    $childIdColumn,
                    $itemTable
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
     * @return ArrayList list of ActivityEntry
     * @throws Exception
     */
    public function getActivityFeed(?int $minVersion = null, ?int $maxVersion = null): ArrayList
    {
        if (is_null($minVersion)) {
            $class = $this->owner->baseClass();
            $id = $this->owner->ID;
            $minVersion = $this->getPublishedVersionNumber($class, $id);

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
