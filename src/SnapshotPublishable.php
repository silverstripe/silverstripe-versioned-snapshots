<?php


namespace SilverStripe\Snapshots;

use SilverStripe\Core\Config\Config;
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
 * @property DataObject|SnapshotPublishable $owner
 */
class SnapshotPublishable extends RecursivePublishable
{
    private static $__cache = [
        'requires' => [],
        'mmlinking' => [],
    ];

    /**
     * Global state to tell all write hooks that a snapshot is in progress.
     * Prevents recursion and duplicity.
     * @var Snapshot
     */
    protected $activeSnapshot = null;

    /**
     * @param $class
     * @param $id
     * @return string
     */
    public static function hash($class, $id)
    {
        return md5($class . $id);
    }

    /**
     * @param DataObject $obj
     * @return string
     */
    public static function hashObject(DataObject $obj)
    {
        return static::hash(get_class($obj), $obj->ID);
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

    /**
     * @param int $sinceVersion
     * @return DataList
     */
    public function getSnapshots($sinceVersion)
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $where = [
            ['ObjectHash = ?' => static::hashObject($this->owner)],
            ['Version >= ?' => $sinceVersion],
        ];

        $result = Snapshot::get()
            ->innerJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
            ->where($where)
            ->sort('Created DESC');

        return $result;
    }

    /**
     * @return DataList
     */
    public function getSnapshotsSinceLastPublish()
    {
        $class = get_class($this->owner);
        $id = $this->owner->ID;
        $publishedVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);

        return $this->owner->getSnapshots($publishedVersion)
            ->exclude([
                'OriginHash' => static::hashObject($this->owner),
            ]);
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
     * @return ArrayList
     */
    public function getActivityFeed()
    {
        $list = ArrayList::create();
        $items = $this->owner->getActivity();
        if (!$items->exists()) {
            return $list;
        }

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
        $hash = static::hashObject($this->owner);
        $snapShotIDs = $this->getSnapshotsSinceLastPublish()->column('ID');
        if (empty($snapShotIDs)) {
            return false;
        }
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        $query = new SQLSelect(
            ['MaxID' => "MAX($itemTable.ID)"],
            $itemTable
        );
        $query->setWhere([
            ['SnapshotID IN (' . DB::placeholders($snapShotIDs) . ')' => $snapShotIDs],
            ['WasPublished = ?' => 0],
            ['WasDeleted = ?' => 0],
            ['ObjectHash != ? ' => $hash]
        ])
            ->setGroupBy('ObjectHash')
            ->setOrderBy('Created DESC')
            ->setLimit(1);

        $result = $query->execute();

        return $result->numRecords() === 1;
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
        /* @var Versioned|DataObject $version */
        $version = Versioned::get_latest_version($this->owner->baseClass(), $this->owner->ID);
        return SnapshotItem::create([
            'ObjectClass' => get_class($this->owner),
            'ObjectID' => $this->owner->ID,
            'WasDraft' => $version->WasDraft,
            'WasDeleted' => $version->WasDeleted || $version->isOnLiveOnly(),
            'Version' => $version->Version,
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
        $snapshots = $this->getSnapshots($this->owner->Version)
            ->filter([
                'OriginHash' => static::hashObject($this->owner),
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
        foreach ($linking as $parentClass => $specs) {
            foreach ($specs as $spec) {
                list ($parentName, $childName) = $spec;
                $parent = $owner->getComponent($parentName);
                $child = $owner->getComponent($childName);
                if ($parent->exists() && $child->exists()) {
                    yield [$parentClass, $parentName, $parent, $child];
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getManyManyLinking()
    {
        /* @var DataObject|SnapshotPublishable $owner */
        $owner = $this->owner;
        if (!isset(self::$__cache['mmlinking'][get_class($owner)])) {
            $config = [];
            $ownerClass = get_class($owner);

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
            self::$__cache['mmlinking'][get_class($owner)] = $config;
        }

        return self::$__cache['mmlinking'][get_class($owner)];
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

        if ($owner->isManyManyLinkingObject()) {
            return true;
        }

        return $owner->findOwners()->exists();
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
     * @param null $origin
     * @return Snapshot
     */
    protected function openSnapshot($origin = null)
    {
        if (!$origin) {
            $origin = $this->owner;
        }
        $snapshot = Snapshot::create([
            'OriginClass' => get_class($origin),
            'OriginID' => $origin->ID,
            'AuthorID' => Security::getCurrentUser()
                ? Security::getCurrentUser()->ID
                : 0
        ]);
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
            $item->LinkedFromObjectClass = get_class($linkedFromObj);
            $item->LinkedFromObjectID = $linkedFromObj->ID;
        }
        if ($linkedToObj) {
            $item->LinkedToObjectClass = get_class($linkedToObj);
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
                if (
                    !is_numeric($spec['before'])
                    || !is_numeric($spec['after'])
                    || $spec['before'] == $spec['after']
                ) {
                    continue;
                }

                $class = $map[$field];
                $result[] = [
                    'previous' => DataObject::get_by_id($class, $spec['before']),
                    'current' => DataObject::get_by_id($class, $spec['after']),
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

            $previousHashes = array_map([static::class, 'hashObject'], $previousOwners);
            $snapshotsToMigrate = $previousOwner->getSnapshotsSinceLastPublish();

            // Todo: bulk update, optimise
            foreach ($snapshotsToMigrate as $snapshot) {
                $itemsToDelete = $snapshot->Items()->filter([
                    'ObjectHash' => $previousHashes
                ]);
                $itemsToDelete->removeAll();

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
