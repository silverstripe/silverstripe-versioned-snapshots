<?php

namespace SilverStripe\Snapshots;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\RecursivePublishable;
use BadMethodCallException;
use SilverStripe\Versioned\Versioned;

/**
 * Class SnapshotPublishable
 * @property DataObject|SnapshotPublishable|Versioned $owner
 */
class SnapshotPublishable extends RecursivePublishable
{
    use SnapshotHasher;

    /**
     * Global state to tell all write hooks that a snapshot is in progress.
     * Prevents recursion and duplicity.
     * @var Snapshot
     */
    protected $activeSnapshot = null;

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
     * @return bool
     */
    public function publishRecursive()
    {
        $this->openSnapshot();
        $result = parent::publishRecursive();
        $this->closeSnapshot();

        return $result;
    }

    public function rollbackRelations($version)
    {
        $this->openSnapshot();
        parent::rollbackRelations($version);
        $this->closeSnapshot();
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
     *
     * @return array list of filters for using in ORM APIs
     */
    public function getSnapshotsBetweenVersionsFilters($min, $max = null)
    {
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $hash = static::hashObjectForSnapshot($this->owner);
        $minShot = SQLSelect::create(
            'MIN("SnapshotID")',
            "\"$itemTable\"",
            [
                '"ObjectHash" = ?' => $hash,
                '"Version" = ?' => $min,
            ]
        );
        $minShotSQL = $minShot->sql($minParams);

        $maxShot = SQLSelect::create(
            'MAX("SnapshotID")',
            "\"$itemTable\"",
            [
                '"ObjectHash" = ?' => $hash,
                '"Version" = ?' => $max,
            ]
        );
        $maxShotSQL = $maxShot->sql($maxParams);

        $condition = $max === null
            ? sprintf('"SnapshotID" >= (%s)', $minShotSQL)
            : sprintf('"SnapshotID" BETWEEN (%s) and (%s)', $minShotSQL, $maxShotSQL);

        $query = SQLSelect::create(
            "\"SnapshotID\"",
            "\"$itemTable\"",
            [
                $condition => $max === null ? $minParams : array_merge($minParams, $maxParams),
                '"ObjectHash"' => $hash,
                'NOT ("Version" = ? AND "WasPublished" = 1)' => $min,
            ]
        );
        $sql = $query->sql($params);

        return [
            sprintf('"SnapshotID" IN (%s)', $sql) => $params
        ];
    }

    public function getActivityBetweenVersions($min, $max = null)
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $items = SnapshotItem::get()
            ->innerJoin($snapshotTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
            ->where(array_merge([
                // Only get the items that were the subject of a user's action
                "\"$snapshotTable\" . \"OriginHash\" = \"$itemTable\".\"ObjectHash\""
            ], $this->getSnapshotsBetweenVersionsFilters($min, $max)))
            ->sort([
                "\"$itemTable\".\"SnapshotID\"" => "ASC"
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
                    'SnapshotID' => $snapShotIDs,
                ])
                ->where(
                    // Only get the items that were the subject of a user's action
                    "\"$snapshotTable\" . \"OriginHash\" = \"$itemTable\".\"ObjectHash\""
                )
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
            if ($obj->isManyManyLinkingObject()) {
                $ownership = $obj->getManyManyOwnership();
                foreach ($ownership as $spec) {
                    list ($parentClass, $parentName, $parent, $child) = $spec;
                    $map[static::hashObjectForSnapshot($child)] = $child;
                }
            } else {
                $map[static::hashObjectForSnapshot($obj)] = $obj;
            }
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
     * @return void
     */
    public function onAfterWrite()
    {
        if (!$this->requiresSnapshot()) {
            return;
        }

        $this->doSnapshot();

        $changes = $this->getChangedOwnership();
        if (!empty($changes)) {
            $this->reconcileOwnershipChanges($changes);
        }
    }

    public function onAfterDelete()
    {
        if ($this->requiresSnapshot()) {
            $this->doSnapshot();
        }
    }

    public function createSnapshotItem()
    {
        /* @var DataObject|Versioned|SnapshotPublishable $owner */
        $owner = $this->owner;
        return SnapshotItem::create([
            'ObjectClass' => $owner->baseClass(),
            'ObjectID' => $owner->ID,
            'WasDraft' => $owner->isModifiedOnDraft(),
            'WasDeleted' => $owner->isOnLiveOnly() || $owner->isArchived(),
            'Version' => $owner->Version,
            'LinkedObjectClass' => null,
            'LinkedObjectID' => 0
        ]);
    }

    public function onAfterPublish()
    {
        if ($this->activeSnapshot) {
            $item = $this->owner->createSnapshotItem();
            $item->WasPublished = true;
            $this->activeSnapshot->Items()->add($item);
        }
    }

    public function onBeforeRevertToLive()
    {
        if ($this->requiresSnapshot()) {
            $this->openSnapshot();
            $this->doSnapshot();
        }
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
     * @return bool
     */
    public function isManyManyLinkingObject()
    {
        return !empty($this->owner->getManyManyLinking());
    }

    /**
     * @return \Generator
     * @throws \Exception
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
        $ownerClass = $owner->baseClass();

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
                if ($spec['through'] !== $ownerClass) {
                    continue;
                }
                if (!isset($config[$class])) {
                    $config[$class][] = [$spec['from'], $spec['to']];
                }
            }
        }

        return $config;
    }

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
        $hash = static::hashObjectForSnapshot($this->owner);

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
            '"ObjectHash" = "OriginHash"',
        ])
            ->setGroupBy(['"ObjectHash"', "\"$itemTable\".\"Created\",  \"$itemTable\".\"ID\""])
            ->setOrderBy("\"$itemTable\".\"Created\",  \"$itemTable\".\"ID\"");

        return $query;
    }

    /**
     * @return bool
     */
    protected function requiresSnapshot()
    {
        $owner = $this->owner;

        // Explicitly blacklist these two, since they get so many writes in this context,
        // and calculating snapshot eligibility is expensive.
        if ($owner instanceof Snapshot || $owner instanceof SnapshotItem) {
            return false;
        }

        if (!$owner->hasExtension(Versioned::class)) {
            return false;
        }

        return !$owner->getNextWriteWithoutVersion();
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function doSnapshot()
    {
        // Block nested snapshots. One user action = one snapshot
        if ($this->activeSnapshot) {
            return null;
        }
        $owner = $this->owner;
        /* @var DataObject|SnapshotPublishable $owner */
        if ($this->isManyManyLinkingObject()) {
            foreach ($owner->getManyManyOwnership() as $spec) {
                /* @var DataObject|SnapshotPublishable $parent */
                list ($parentClass, $parentName, $parent, $child) = $spec;
                $this->openSnapshot();
                $this->addToSnapshot($owner, $parent, $child);
                foreach ($parent->findOwners() as $owner) {
                    $this->addToSnapshot($owner);
                }
                $this->closeSnapshot();
            }

            return null;
        }

        $this->openSnapshot();
        $this->addToSnapshot($owner);

        foreach ($this->owner->findOwners() as $owner) {
            $this->addToSnapshot($owner);
        }

        $this->closeSnapshot();
    }

    /**
     * @param $origin
     * @return Snapshot
     */
    protected function createSnapshot($origin = null)
    {
        $snapshot = Snapshot::create([
            'OriginClass' => $origin->baseClass(),
            'OriginID' => $origin->ID,
            'AuthorID' => Security::getCurrentUser()
                ? Security::getCurrentUser()->ID
                : 0
        ]);

        return $snapshot;
    }

    /**
     * @param null $origin
     * @return Snapshot
     */
    protected function openSnapshot($origin = null)
    {
        if (!$origin) {
            $origin = $this->owner;
        }
        $snapshot = $this->createSnapshot($origin);
        $snapshot->write();
        $this->activeSnapshot = $snapshot;

        return $snapshot;
    }

    /**
     * @param DataObject $obj
     * @param DataObject|null $linkedFromObj
     * @param DataObject|null $linkedToObj
     */
    protected function addToSnapshot(DataObject $obj, $linkedFromObj = null, $linkedToObj = null)
    {
        if (!$this->activeSnapshot) {
            throw new BadMethodCallException('Cannot call addToSnapshot() before openSnapshot()');
        }

        if (!$obj->hasExtension(RecursivePublishable::class)) {
            throw new BadMethodCallException(sprintf(
                'addToSnapshot() only accepts objects with the %s extension',
                RecursivePublishable::class
            ));
        }
        /* @var SnapshotPublishable|DataObject $obj */
        $item = $obj->createSnapshotItem();
        if ($linkedFromObj) {
            $item->LinkedFromObjectClass = $linkedFromObj->baseClass();
            $item->LinkedFromObjectID = $linkedFromObj->ID;
        }
        if ($linkedToObj) {
            $item->LinkedToObjectClass = $linkedToObj->baseClass();
            $item->LinkedToObjectID = $linkedToObj->ID;
        }

        $this->activeSnapshot->Items()->add($item);
    }

    /**
     * @return void
     */
    protected function closeSnapshot()
    {
        $this->activeSnapshot = null;
    }

    /**
     * @return array
     */
    protected function getChangedOwnership()
    {
        $owner = $this->owner;
        $hasOne = $owner->hasOne();
        $fields = array_map(function ($field) {
            return $field . 'ID';
        }, array_keys($hasOne));
        $changed = $owner->getChangedFields($fields);
        $map = array_combine($fields, array_values($hasOne));
        $result = [];
        foreach ($fields as $field) {
            if (isset($changed[$field])) {
                $spec = $changed[$field];

                if (is_null($spec['before']) || is_null($spec['after']) || $spec['before'] == $spec['after']) {
                    continue;
                }

                $class = $map[$field];

                if (!$previous = DataObject::get_by_id($class, $spec['before'])) {
                    continue;
                }

                if (!$current = DataObject::get_by_id($class, $spec['after'])) {
                    continue;
                }

                $result[] = [
                    'previous' => $previous,
                    'current' => $current,
                ];
            }
        }

        return $result;
    }

    /**
     * If ownership has changed, relocate the activity to the new owner.
     * There is no point to showing the old owner as "modified" since there
     * is nothing the user can do about it. Recursive publishing the old owner
     * will not affect this record, as it is no longer in its ownership graph.
     *
     * @param array $changes
     */
    protected function reconcileOwnershipChanges($changes)
    {
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
                        $item = $owner->createSnapshotItem();
                        $item->SnapshotID = $snapshot->ID;
                        $item->write();
                    }
                }
            }
        }
    }
}
