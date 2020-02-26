<?php


namespace SilverStripe\Snapshots;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use InvalidArgumentException;

class RelationDiffer
{
    use Injectable;

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
    private $previousVersionMapping = [];

    /**
     * @var array
     */
    private $currentVersionMapping = [];

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
            throw new InvalidArgumentException(sprintf('DataObject must use the %s extension', SnapshotPublishable::class));
        }
        $this->relationClass = $relationClass;
        $this->relationType = $relationType;
        $this->previousVersionMapping = $previousVersionMapping;
        $this->currentVersionMapping = $currentVersionMapping;
        $this->diff();
    }

    /**
     * @return void
     */
    private function diff(): void
    {
        $currentIDs = array_keys($this->currentVersionMapping);
        $previousIDs = array_keys($this->previousVersionMapping);

        $this->added = array_diff($currentIDs, $previousIDs);
        $this->removed = array_diff($previousIDs, $currentIDs);

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
