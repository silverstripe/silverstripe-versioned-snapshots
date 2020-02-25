<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;

class PublishHandler extends Handler
{
    use SnapshotHasher;

    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $snapshot = parent::createSnapshot($context);
        if (!$snapshot) {
            return null;
        }
        $record = $this->getRecordFromContext($context);
        if (!$record) {
            return null;
        }
        // Refresh the record so we get the new version
        $record = DataObject::get_by_id(get_class($record), $record->ID, false);
        if (!$record) {
            return null;
        }

        foreach ($snapshot->Items() as $item) {
            // If it's the origin item, set published state.
            if (static::hashSnapshotCompare($item->getItem(), $record)) {
                $item->WasPublished = true;
                $item->Version = $record->Version;
            }
        }

        return $snapshot;
    }
}
