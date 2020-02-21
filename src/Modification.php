<?php


namespace SilverStripe\Snapshots;


use SilverStripe\ORM\DataObject;
use InvalidArgumentException;

class Modification
{
    /**
     * @var DataObject
     */
    private $record;

    /**
     * @var string
     */
    private $activityType;

    /**
     * Modification constructor.
     * @param DataObject $record
     * @param string $activityType
     */
    public function __construct(DataObject $record, string $activityType)
    {
        $this->record = $record;
        $this->setActivityType($activityType);
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setActivityType(string $type): self
    {
        if (!in_array($type, [
            ActivityEntry::ADDED,
            ActivityEntry::CREATED,
            ActivityEntry::MODIFIED,
            ActivityEntry::REMOVED,
            ActivityEntry::PUBLISHED,
            ActivityEntry::DELETED,
        ])) {
            throw new InvalidArgumentException(sprintf('%s is not an allowed activity type'), $type);
        }

        $this->activityType = $type;

        return $this;
    }

    public function getRecord(): DataObject
    {
        return $this->record;
    }

    public function getActivityType(): string
    {
        return $this->activityType;
    }

    /**
     * @param DataObject $record
     * @return self
     */
    public function setRecord(DataObject $record): self
    {
        $this->record = $record;
        return $this;
    }


}
