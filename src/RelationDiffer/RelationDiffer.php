<?php

namespace SilverStripe\Snapshots\RelationDiffer;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\SnapshotPublishable;

/**
 * Capability to find differences between versions of relations
 */
class RelationDiffer
{

    use Injectable;

    private string $relationClass;

    private string $relationType;

    private array $previousVersionMapping = [];

    private array $currentVersionMapping = [];

    private array $added = [];

    private array $removed = [];

    private array $changed = [];

    /**
     * RelationDiffer constructor.
     *
     * @var string $relationClass
     * @var string $relationTypes
     * @var array $previousVersionMapping
     * @var array $currentVersionMapping
     */
    public function __construct(
        string $relationClass,
        string $relationType,
        array $previousVersionMapping = [],
        array $currentVersionMapping = []
    ) {
        if (!is_subclass_of($relationClass, DataObject::class)) {
            throw new InvalidArgumentException(sprintf('%s is not a DataObject', $relationClass));
        }

        if (!$relationClass::singleton()->hasExtension(SnapshotPublishable::class)) {
            throw new InvalidArgumentException(
                sprintf('DataObject must use the %s extension', SnapshotPublishable::class)
            );
        }

        $this->relationClass = $relationClass;
        $this->relationType = $relationType;
        $this->previousVersionMapping = $previousVersionMapping;
        $this->currentVersionMapping = $currentVersionMapping;
        $this->diff();
    }

    private function diff(): void
    {
        $currentIDs = array_keys($this->currentVersionMapping);
        $previousIDs = array_keys($this->previousVersionMapping);

        $this->added = array_values(array_diff($currentIDs, $previousIDs));
        $this->removed = array_values(array_diff($previousIDs, $currentIDs));

        $changed = [];

        foreach ($this->previousVersionMapping as $prevID => $prevVersion) {
            // Record no longer exists. Not a change.
            if (!isset($this->currentVersionMapping[$prevID])) {
                continue;
            }

            $currentVersion = $this->currentVersionMapping[$prevID];

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

    public function hasChanges(): bool
    {
        return count($this->added) > 0 || count($this->removed) > 0 || count($this->changed) > 0;
    }

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

    public function getRelationClass(): string
    {
        return $this->relationClass;
    }

    public function getRelationType(): string
    {
        return $this->relationType;
    }

    public function getAdded(): array
    {
        return $this->added;
    }

    public function getRemoved(): array
    {
        return $this->removed;
    }

    public function getChanged(): array
    {
        return $this->changed;
    }

    public function isAdded(int $id): bool
    {
        return in_array($id, $this->getAdded());
    }

    public function isRemoved(int $id): bool
    {
        return in_array($id, $this->getRemoved());
    }

    public function isChanged(int $id): bool
    {
        return in_array($id, $this->getChanged());
    }
}
