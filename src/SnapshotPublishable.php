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
     * @return DataList
     */
    public function getSnapshotsSinceLastPublish()
    {
        $class = get_class($this->owner);
        $id = $this->owner->ID;

        $publishedVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);
        $hash = static::hash($class, $id);
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $result = Snapshot::get()
            ->innerJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
            ->where([
                // Only snapshots that this record was involved in
                ['ObjectHash = ?' => $hash],
                // After it was published
                ['Version >= ?' => $publishedVersion],
                // But not snapshots that were instantiated by itself.
                // Making a change to an intermediate node should only affect its owners' activity,
                // not its owned nodes.
                ['OriginHash != ?' => $hash],
            ])
            ->sort('Created DESC');

        return $result;
    }

    /**
     * @return DataList|ArrayList
     */
    public function getActivity()
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        $snapShotIDs = $this->owner->getSnapshotsSinceLastPublish()->column('ID');

        if(!empty($snapShotIDs)) {
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
                ->sort('Created ASC');

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
            ->setOrderBy('Created DESC');

        $result = $query->execute();

        return $result->numRecords() > 0;
    }

    /**
     * Returns a mix of Version objects and SnapshotItem
     * @todo Make this a homogeneous list of types
     * @return ArrayList
     */
    public function getHistoryIncludingOwned()
    {
        $class = get_class($this->owner);
        $id = $this->owner->ID;

        $list = ArrayList::create();
        $versions = Versioned::get_all_versions($class, $id);

        foreach ($versions as $version) {
            $list->push($version);
        }
        $snapshots = $this->owner->getSnapshotsSinceLastPublish();
        foreach ($snapshots as $snapshot) {
            $list->push($snapshot);
        }

        return $list;
    }

    /**
     * @return void
     */
    protected function doSnapshot()
    {
        if ($this->activeSnapshot) {
            return;
        }
        /* @var DataObject|SnapshotPublishable $owner */
        $owner = $this->owner;
        $owners = $owner->findOwners();
        if (!$owners->exists()) {
            if (!$owner->isManyManyLinkingObject()) {
                return;
            }

            foreach($owner->getManyManyOwnership() as $spec) {
                /* @var DataObject|SnapshotPublishable $parent */
                list ($parentClass, $parentName, $parent, $child) = $spec;
                $this->openSnapshot();
                $this->addToSnapshot($owner, $parent, $child);
                foreach($parent->findOwners() as $owner) {
                    $this->addToSnapshot($owner);
                }
                $this->closeSnapshot();

            }

            return;
        }

        $this->openSnapshot();
        $this->addToSnapshot($owner);

        foreach ($owners as $owner) {
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
     * @param null $linkedFromObj
     * @param null $linkedToObj
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

    protected function closeSnapshot()
    {
        $this->activeSnapshot = null;
    }

    public function onAfterWrite()
    {
        $this->doSnapshot();
    }

    public function onAfterDelete()
    {
        $this->doSnapshot();
    }

    public function createSnapshotItem($publish = false)
    {
        $version = Versioned::get_latest_version($this->owner->baseClass(), $this->owner->ID);
        return SnapshotItem::create([
            'ObjectClass' => get_class($this->owner),
            'ObjectID' => $this->owner->ID,
            'WasPublished' => $publish,
            'WasDraft' => $version->WasDraft,
            'WasDeleted' => $version->WasDeleted,
            'Version' => $version->Version,
            'LinkedObjectClass' => null,
            'LinkedObjectID' => 0
        ]);
    }

    public function onAfterPublish()
    {
        if ($this->activeSnapshot) {
            $this->activeSnapshot->Items()->add($this->owner->createSnapshotItem(true));
        }

    }

    /**
     * @return array
     */
    public function getManyManyLinking()
    {
        /* @var DataObject|SnapshotPublishable $owner */
        $owner = $this->owner;
        $cached = $owner->config()->get('many_many_linking');
        if ($cached) {
            return $cached;
        }

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
        // persistent cache with flushable?
        Config::modify()->set($ownerClass, 'many_many_linking', $config);

        return $config;

    }

    /**
     * @return bool
     */
    public function isManyManyLinkingObject()
    {
        return !empty($this->owner->getManyManyLinking());
    }

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
}