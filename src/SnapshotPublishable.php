<?php

namespace SilverStripe\Snapshots;

use BadMethodCallException;
use Exception;
use Generator;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Security;
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
        $publishedVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);
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
    public function getSnapshotsBetweenVersionsFilters($min, $max = null, $includeAll = false)
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
            $condtionStatement[] = 'Modification = 1';
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
     * @return DataList|ArrayList
     */
    public function getActivity()
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        $snapShotIDs = $this->owner->getSnapshotsSinceLastPublish()->column('ID');

        if (!empty($snapShotIDs)) {
            $result = SnapshotItem::get()
                ->innerJoin($snapshotTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
                ->filter([
                    // Only relevant snapshots
                    "\"$itemTable\".\"SnapshotID\"" => $snapShotIDs,

                ])
                ->whereAny([
                    // Only get the items that were the subject of a user's action
                    "\"$snapshotTable\" . \"OriginHash\" = \"$itemTable\".\"ObjectHash\"",
                    "\"$itemTable\".\"ParentID\" != 0",
                ])
                ->sort([
                    "\"$snapshotTable\".\"Created\"" => "ASC",
                    "\"$snapshotTable\".\"ID\"" => "ASC"
                ]);

            return $result;
        }

        return ArrayList::create();
    }

    /**
     * Returns a list of ActivityEntry ordered by creation datetime
     *
     * @param int|null $minVersion version to start with (or last published if null)
     * @param int|null $maxVersion version to end with (or till the end, including everything unpublished)
     *
     * @return ArrayList list of ActivityEntry
     */
    public function getActivityFeed($minVersion = null, $maxVersion = null)
    {
        if (is_null($minVersion)) {
            $class = $this->owner->baseClass();
            $id = $this->owner->ID;
            $minVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);

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
        $minVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);

        if (is_null($minVersion)) {
            $minVersion = 1;
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
     * @param int|string $snapshot A snapshot ID or  date formatted string
     * @return DataObject
     */
    public function getAtSnapshot($snapshot)
    {
        return static::get_at_snapshot($this->owner->baseClass(), $this->owner->ID, $snapshot);
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
     * @return array
     * @throws Exception
     */
    public function getManyManyOwnership()
    {
        /* @var DataObject|SnapshotPublishable $owner */
        $owner = $this->owner;
        $linking = $owner->getManyManyLinking();
        $r = [];
        foreach ($linking as $parentClass => $specs) {
            foreach ($specs as $spec) {
                list ($parentName, $childName) = $spec;
                $parent = $owner->getComponent($parentName);
                $child = $owner->getComponent($childName);
                if ($parent->exists() && $child->exists()) {
                    $r[] = [$parentClass, $parentName, $parent, $child];
                }
            }
        }

        return $r;
    }

    /**
     * @return array
     */
    public function getManyManyLinking()
    {
        /* @var DataObject|SnapshotPublishable $owner */
        $owner = $this->owner;
        $config = [];

        // Has to have two has_ones
        $hasOnes = $owner->hasOne();
        if (sizeof($hasOnes) < 2) {
            return $config;
        }

        foreach ($hasOnes as $name => $class) {
            /* @var DataObject $sng */
            $sng = Injector::inst()->get($class);
            foreach ($sng->manyMany() as $component => $spec) {
                if (!is_array($spec)) {
                    continue;
                }
                if (!($owner instanceof $spec['through'])) {
                    continue;
                }
                if (!isset($config[$class])) {
                    $config[$class][] = [$spec['from'], $spec['to']];
                }
            }
        }

        return $config;
    }

    /**
     * Get the IDs that have been added and removed for each many_many component since the given version
     * @return array
     */
    public function diffManyMany(): array
    {
        $owner = $this->owner;
        $diff = [];
        // Get the last time this record was in a snapshot. Doesn't matter where or why. We just need a
        // timestamp prior to now, because the Version may be unchanged.
        $lastSnapshot = SnapshotItem::get()->filter([
            'ObjectHash' => static::hashObjectForSnapshot($owner),
        ])->max('LastEdited');
        foreach ($owner->config()->get('many_many') as $component => $spec) {
            $currentIDs = $owner->$component()->column('ID');
            if ($lastSnapshot) {
                $previousIDs = Versioned::withVersionedMode(function () use ($lastSnapshot, $owner, $component) {
                    Versioned::reading_archived_date($lastSnapshot);
                    return $owner->$component()->column('ID');
                });
            } else {
                $previousIDs = [];
            }

            $added = array_diff($currentIDs, $previousIDs);
            $removed = array_diff($previousIDs, $currentIDs);
            $mmData = $owner->getSchema()->manyManyComponent(get_class($owner), $component);

            if (empty($added) && empty($removed)) {
                continue;
            }

            $diff[$mmData['childClass']] = [
                'added' => $added,
                'removed' => $removed,
            ];
        }

        return $diff;
    }

    /**
     * @param $snapshot
     */
    public function rollbackOwned($snapshot)
    {
        $owner = $this->owner;
        // Rollback recursively
        foreach ($owner->findOwned(false) as $object) {
            if ($object->hasExtension(SnapshotVersioned::class)) {
                $object->doRollbackToSnapshot($snapshot);
            } else {
                $object->rollbackOwned($snapshot);
            }
        }
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
    private function getChangedOwnership(DataObject $previous): array
    {
        $owner = $this->owner;
        $hasOne = $owner->hasOne();
        $fields = array_map(function ($field) {
            return $field . 'ID';
        }, array_keys($hasOne));
        $map = array_combine($fields, array_values($hasOne));
        $result = [];
        foreach ($fields as $field) {
            $previousValue = $previous->$field;
            $currentValue = $owner->$field;
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
}
