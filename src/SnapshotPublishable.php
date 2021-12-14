<?php

namespace SilverStripe\Snapshots;

use Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;

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
     * A more resillient wrapper for the Versioned function that holds up against unstaged versioned
     * implementations
     * @param string $class
     * @param int $id
     * @return int|null
     */
    public static function get_published_version_number(string $class, int $id): ?int
    {
        $inst = $class::singleton();
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
     * @param $class
     * @param $id
     * @param string|int $snapshot A snapshot ID or a Y-m-d h:i:s date formatted string
     * @return DataObject
     */
    public static function get_at_snapshot($class, $id, $snapshot)
    {
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $snapshotDate = null;
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
    public static function get_last_snapshot_item(string $class, int $id): ? DataObject
    {
        return SnapshotItem::get()->filter([
            'ObjectHash' => static::hashForSnapshot($class, $id)
        ])
            ->sort('Created', 'DESC')
            ->first();
    }

    /**
     * @return DataList
     */
    public static function getSnapshots()
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $result = Snapshot::get()
            ->innerJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"");

        return $result;
    }

    /**
     * @return DataList
     */
    public function getRelevantSnapshots()
    {
        $snapshots = $this->owner->getSnapshots()
            ->where([
                ['"ObjectHash" = ?' => static::hashObjectForSnapshot($this->owner)]
            ]);

        $this->owner->extend('updateRelevantSnapshots', $snapshots);

        return $snapshots;
    }

    /**
     * @param int $sinceVersion
     * @return DataList
     */
    public function getSnapshotsSinceVersion($sinceVersion)
    {
        $sinceVersion = (int) $sinceVersion;
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $where = [
            // last published version
            ['"Version" >= ?' => $sinceVersion],

            // is not a snapshot of the last publishing
            [
                sprintf(
                    '"SnapshotID" >
                    COALESCE((
                      SELECT
                        MAX("SnapshotID")
                      FROM
                        "%s"
                      WHERE
                        "ObjectHash" = ?
                      AND
                        "Version" = ?
                      AND
                        "WasPublished" = 1
                    ), 0)',
                    $itemTable
                ) => [
                    static::hashObjectForSnapshot($this->owner),
                    $sinceVersion
                ]
            ],
        ];

        $result = $this->owner->getRelevantSnapshots()
            ->where($where);

        return $result;
    }

    /**
     * @return DataList
     */
    public function getSnapshotsSinceLastPublish()
    {
        $class = $this->owner->baseClass();
        $id = $this->owner->ID;
        $publishedVersion = static::get_published_version_number($class, $id);
        $snapshots = $this->owner->getSnapshotsSinceVersion($publishedVersion);

        return $snapshots;
    }

    /**
     * Generate ORM filters for snapshots between 2 versions
     * If $max is null, includes everything unpublished too
     *
     * @param int $min Minimal version to start looking with (inclusive)
     * @param int|null $max Maximal version to look until (inclusive)
     * @param bool $includeAll Include snapshot items that have no modifications
     *
     * @return array list of filters for using in ORM APIs
     */
    protected function getSnapshotsBetweenVersionsFilters($min, $max = null, $includeAll = false)
    {
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $hash = static::hashObjectForSnapshot($this->owner);
        $minShot = SQLSelect::create(
            "MIN(\"$itemTable\".\"SnapshotID\")",
            "\"$itemTable\"",
            [
                '"ObjectHash" = ?' => $hash,
                '"Version" = ?' => $min,
            ]
        );
        $minShotSQL = $minShot->sql($minParams);

        $maxShot = SQLSelect::create(
            "MAX(\"$itemTable\".\"SnapshotID\")",
            "\"$itemTable\"",
            [
                '"ObjectHash" = ?' => $hash,
                '"Version" = ?' => $max,
            ]
        );
        $maxShotSQL = $maxShot->sql($maxParams);

        $condition = $max === null
            ? sprintf("\"$itemTable\".\"SnapshotID\" >= (%s)", $minShotSQL)
            : sprintf("\"$itemTable\".\"SnapshotID\" BETWEEN (%s) and (%s)", $minShotSQL, $maxShotSQL);

        $condtionStatement = [
            $condition => $max === null ? $minParams : array_merge($minParams, $maxParams),
            '"ObjectHash"' => $hash,
            'NOT ("Version" = ? AND "WasPublished" = 1)' => $min,
        ];
        if (!$includeAll) {
            $condtionStatement[] = '"Modification" = 1';
        }

        $query = SQLSelect::create(
            "\"$itemTable\".\"SnapshotID\"",
            "\"$itemTable\"",
            $condtionStatement
        );
        $sql = $query->sql($params);

        return [
            sprintf("\"$itemTable\".\"SnapshotID\" IN (%s)", $sql) => $params
        ];
    }

    /**
     * @return boolean
     */
    public function hasOwnedModifications()
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return false;
        }

        $class = $this->owner->baseClass();
        $id = $this->owner->ID;
        $minVersion = static::get_published_version_number($class, $id);

        if (is_null($minVersion)) {
            return false; //Draft page.
        }

        $result = SnapshotItem::get()
            ->where($this->getSnapshotsBetweenVersionsFilters($minVersion, null));

        return $result->exists();
    }

    /**
     * @return int
     */
    public function getPublishableItemsCount()
    {
        $snapShotIDs = $this->getSnapshotsSinceLastPublish()->column('ID');
        if (empty($snapShotIDs)) {
            return 0;
        }
        return $this->publishableItemsQuery($snapShotIDs)->execute()->numRecords();
    }

    /**
     * @return ArrayList
     */
    public function getPublishableObjects()
    {
        $snapShotIDs = $this->getSnapshotsSinceLastPublish()->column('ID');
        if (empty($snapShotIDs)) {
            return ArrayList::create();
        }

        $query = $this->publishableItemsQuery($snapShotIDs);
        $query->addSelect($groupByFields = ['"ObjectClass"', '"ObjectID"']);
        $query->addGroupBy($groupByFields);

        $items = $query->execute();
        $map = [];

        foreach ($items as $row) {
            $class = $row['ObjectClass'];
            $id = $row['ObjectID'];
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
     * @return DataObject
     */
    public function getAtSnapshot($snapshot)
    {
        return static::get_at_snapshot($this->owner->baseClass(), $this->owner->ID, $snapshot);
    }

    /**
     * @return DataObject|null
     */
    public function getAtLastSnapshot()
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
    public function onAfterRevertToLive()
    {
        $snapshots = $this->getSnapshotsSinceVersion($this->owner->Version)
            ->filter([
                'OriginHash' => static::hashObjectForSnapshot($this->owner),
            ]);

        $snapshots->removeAll();
    }

    /**
     * @return SnapshotItem|null
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
        $lastSnapshot = SnapshotItem::get()->filter([
            'ObjectHash' => static::hashObjectForSnapshot($this->owner),
        ])->max('LastEdited');

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
        $previous = $this->getPreviousSnapshotVersion();

        return $previous ? $previous->Version < $this->owner->Version : true;
    }

    /**
     * @return RelationDiffer[]
     * @todo Memoise / cache
     */
    public function getRelationDiffs(): array
    {
        $diffs = [];
        $previousTracking = $this->owner->atPreviousSnapshot(function ($date) {
            if (!$date) {
                return [];
            }
            $record = DataObject::get_by_id($this->owner->baseClass(), $this->owner->ID, false);
            if (!$record) {
                return [];
            }
           /* @var DataObject|SnapshotPublishable $record */
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
    public function hasRelationChanges($cache = true): bool
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
        $previous = null;
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
     * @return SQLSelect
     */
    protected function publishableItemsQuery($snapShotIDs)
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $query = new SQLSelect(
            ['MaxID' => "MAX(\"$itemTable\".\"ID\")"],
            "\"$itemTable\""
        );
        $query->addInnerJoin(
            $snapshotTable,
            "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\""
        );
        $query->setWhere([
            ['"SnapshotID" IN (' . DB::placeholders($snapShotIDs) . ')' => $snapShotIDs],
            ['"WasPublished" = ?' => 0],
            ['"WasDeleted" = ?' => 0],
            '"ObjectHash" = "OriginHash" OR "ParentID" != 0',
        ])
            ->setGroupBy(['"ObjectHash"', "\"$itemTable\".\"Created\",  \"$itemTable\".\"ID\""])
            ->setOrderBy("\"$itemTable\".\"Created\",  \"$itemTable\".\"ID\"");

        return $query;
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
     * @param $min
     * @param null $max
     * @return DataList
     */
    public function getActivityBetweenVersions($min, $max = null)
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $items = SnapshotItem::get()
            ->innerJoin($snapshotTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
            ->leftJoin($itemTable, "\"ChildItem\".\"ParentID\" = \"$itemTable\".\"ID\"", "ChildItem")
            ->where([
                $this->getSnapshotsBetweenVersionsFilters($min, $max),
                // Only get the items that were the subject of a user's action
                "(
                    \"$snapshotTable\" . \"OriginHash\" = \"$itemTable\".\"ObjectHash\" AND
                    \"ChildItem\".\"ID\" IS NULL
                ) OR (
                    \"$snapshotTable\" . \"OriginHash\" != \"$itemTable\".\"ObjectHash\" AND
                    \"$itemTable\".\"ParentID\" != 0
                )"
            ])
            ->sort([
                "\"$itemTable\".\"SnapshotID\"" => "ASC",
                "\"$itemTable\".\"ID\"" => "ASC",
            ]);

        return $items;
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
    public function getActivityFeed($minVersion = null, $maxVersion = null)
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
