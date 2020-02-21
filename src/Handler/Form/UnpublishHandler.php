<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;

class UnpublishHandler extends Handler
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
        foreach ($snapshot->Items() as $item) {
            // If it's the origin item, set published state.
            if (static::hashSnapshotCompare($item->getItem(), $record)) {
                $item->WasUnpublished = true;
            }
        }

        return $snapshot;
    }
}
