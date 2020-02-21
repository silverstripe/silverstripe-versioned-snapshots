<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Versioned\Versioned;

class SaveHandler extends Handler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        /* @var SnapshotPublishable|DataObject $record */
        $record = $this->getRecordFromContext($context);
        if ($record === null) {
            return parent::createSnapshot($context);
        }

        if (!$record->hasExtension(Versioned::class)) {
            return parent::createSnapshot($context);
        }

        if ($record instanceof SnapshotEvent) {
            return parent::createSnapshot($context);
        }

        if ($record->isModifiedSinceLastSnapshot()) {
            return parent::createSnapshot($context);
        }

        return null;
    }
}
