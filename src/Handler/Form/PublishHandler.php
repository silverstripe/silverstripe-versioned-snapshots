<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Versioned\ChangeSet;

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

        // Get the most recent change set to find out what was published
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
