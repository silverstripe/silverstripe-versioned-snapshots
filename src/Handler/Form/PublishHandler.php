<?php

namespace SilverStripe\Snapshots\Handler\Form;

use Exception;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\Versioned;

class PublishHandler extends Handler
{

    use SnapshotHasher;

    /**
     * @throws ValidationException
     * @throws Exception
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();

        if ($action === null) {
            return null;
        }

        $record = $this->getRecordFromContext($context);

        if ($record === null || !$record->hasExtension(Versioned::class)) {
            return null;
        }

        $snapshot = Snapshot::singleton()->createSnapshot($record);

        // Get the most recent change set to find out what was published
        /** @var ChangeSet $changeSet */
        $changeSet = ChangeSet::get()->filter([
            'State' => ChangeSet::STATE_PUBLISHED,
            'IsInferred' => true,
        ])
            ->sort('Created', 'DESC')
            ->first();

        if ($changeSet) {
            foreach ($changeSet->Changes() as $item) {
                foreach ($item->findReferenced() as $obj) {
                    $snapshot->addObject($obj);
                }
            }
        }

        foreach ($snapshot->Items() as $i) {
            $i->WasPublished = true;
        }

        return $snapshot;
    }
}
