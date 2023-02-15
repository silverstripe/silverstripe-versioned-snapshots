<?php

namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
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
            $itemObject = $item->getItem();

            if ($itemObject && !$this->hashSnapshotCompare($itemObject, $record)) {
                continue;
            }

            // If it's the origin item, set published state.
            $item->WasUnpublished = true;
        }

        return $snapshot;
    }
}
