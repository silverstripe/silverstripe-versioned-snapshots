<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

class PublishHandler extends Handler
{
    use SnapshotHasher;

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
        $changeSetTable = DataObject::getSchema()->tableName(ChangeSet::class);
        $changeSetItemTable = DataObject::getSchema()->tableName(ChangeSetItem::class);
        // Get the most recent change set to find out what was published
        $changeSetItem = ChangeSetItem::get()->filter([
            'State' => ChangeSet::STATE_PUBLISHED,
            'IsInferred' => true,
            'ObjectID' => $record->ID,
            'ObjectClass' => $record->baseClass(),
        ])
            ->innerJoin($changeSetTable, "\"$changeSetTable\".\"ID\" = \"$changeSetItemTable\".\"ChangeSetID\"")
            ->sort('Created', 'DESC')
            ->first();

        // Ensure this publish event contained real changes
        if (!$changeSetItem || !($changeSetItem->VersionBefore < $changeSetItem->VersionAfter)) {
            return null;
        }

        foreach ($changeSetItem->ChangeSet()->Changes() as $item) {
            foreach ($item->findReferenced() as $obj) {
                $snapshot->addObject($obj);
            }
        }

        foreach ($snapshot->Items() as $i) {
            $i->WasPublished = true;
        }

        return $snapshot;
    }
}
