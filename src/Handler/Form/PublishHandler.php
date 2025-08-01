<?php

namespace SilverStripe\Snapshots\Handler\Form;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

/**
 * Event hook for @see Form
 */
class PublishHandler extends Handler
{

    use SnapshotHasher;

    /**
     * @throws ValidationException
     * @throws Exception
     * @throws NotFoundExceptionInterface
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
        $changeSet = ChangeSet::get()
            ->filter([
                'State' => ChangeSet::STATE_PUBLISHED,
                'IsInferred' => true,
            ])
            ->sort('Created', 'DESC')
            ->first();

        if ($changeSet) {
            /** @var ChangeSetItem $item */
            foreach ($changeSet->Changes() as $item) {
                foreach ($item->findReferenced() as $obj) {
                    $snapshot->addObject($obj);
                }
            }
        }

        /** @var SnapshotItem $item */
        foreach ($snapshot->Items() as $item) {
            $item->WasPublished = true;
        }

        return $snapshot;
    }
}
