<?php


namespace SilverStripe\Snapshots;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use InvalidArgumentException;
use SilverStripe\Versioned\Versioned;

class RelationDiffer
{
    use Injectable;

    /**
     * @var DataObject|SnapshotPublishable|Versioned
     */
    private $owner;

    /**
     * @var string
     */
    private $relation;

    /**
     * @var string
     */
    private $relationClass;

    /**
     * @var string
     */
    private $relationType;

    /**
     * @var array
     */
    private $added = [];

    /**
     * @var array
     */
    private $removed = [];

    /**
     * @var array
     */
    private $changed = [];

    /**
     * RelationDiffer constructor.
     * @param DataObject $owner
     * @param string $relation
     */
    public function __construct(DataObject $owner, string $relation)
    {
        if (!$owner->hasExtension(SnapshotPublishable::class)) {
            throw new InvalidArgumentException(sprintf('DataObject must use the %s extension', SnapshotPublishable::class));
        }
        $relationClass = $owner->getRelationClass($relation);
        $relationType = $owner->getRelationType($relation);
        if (!$relationClass || !$relationType) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a valid relation on %s',
                $relation,
                get_class($owner)
            ));
        }

        $this->owner = $owner;
        $this->relationClass = $relationClass;
        $this->relationType = $relationType;
        $this->relation = $relation;

        $this->diff();
    }

    /**
     * @return void
     */
    private function diff(): void
    {
        $owner = $this->owner;
        $relation = $this->relation;
        $currentVersionMapping = $this->owner->$relation()->map('ID', 'Version')->toArray();
        $prevVersionMapping = $owner->atPreviousSnapshot(
            function (?string $timestamp) {
                $relation = $this->relation;
                if ($timestamp) {
                    return $this->owner->$relation()->map('ID', 'Version')->toArray();
                }

                return [];
            });

        $currentIDs = array_keys($currentVersionMapping);
        $previousIDs = array_keys($prevVersionMapping);

        $this->added = array_diff($currentIDs, $previousIDs);
        $this->removed = array_diff($previousIDs, $currentIDs);

        $changed = [];

        foreach ($prevVersionMapping as $prevID => $prevVersion) {
            // Record no longer exists. Not a change.
            if (!isset($currentVersionMapping[$prevID])) {
                continue;
            }
            $currentVersion = $currentVersionMapping[$prevID];
            // Versioned extension not applied
            if ($prevVersion === null && $currentVersion === null) {
                continue;
            }
            // New version is higher than old version. It's a change.
            if ($currentVersion > $prevVersion) {
                $changed[] = $prevID;
            }
        }

        $this->changed = $changed;
    }

    /**
     * @return bool
     */
    public function hasChanges(): bool
    {
        return !empty($this->added) || !empty($this->removed) || !empty($this->changed);
    }

    /**
     * @return array
     */
    public function getRecords(): array
    {
        if (!$this->hasChanges()) {
            return [];
        }
        $ids = array_merge(
            $this->added,
            $this->removed,
            $this->changed
        );
        return DataList::create($this->relationClass)->byIDs($ids)->toArray();
    }

    /**
     * @return array Modification[]
     */
    public function getModifications(): array
    {
        $modifications = [];
        $map = [
            'added' => ActivityEntry::ADDED,
            'removed' => ActivityEntry::REMOVED,
            'changed' => ActivityEntry::MODIFIED,
        ];
        foreach ($map as $property => $state) {
            $ids = $this->$property;
            if (!empty($ids)) {
                $records = DataList::create($this->relationClass)->byIDs($ids);
                foreach($records as $record) {
                    $modifications[] = new Modification($record, $state);
                }
            }
        }

        return $modifications;
    }

    /**
     * @return DataObject|SnapshotPublishable|Versioned
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * @return string
     */
    public function getRelationClass(): string
    {
        return $this->relationClass;
    }

    /**
     * @return string
     */
    public function getRelationType(): string
    {
        return $this->relationType;
    }

    /**
     * @return array
     */
    public function getAdded(): array
    {
        return $this->added;
    }

    /**
     * @return array
     */
    public function getRemoved(): array
    {
        return $this->removed;
    }

    /**
     * @return array
     */
    public function getChanged(): array
    {
        return $this->changed;
    }


}
