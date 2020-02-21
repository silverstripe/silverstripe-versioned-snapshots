<?php


namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Versioned\Versioned;

class ArchiveElementHandler extends Handler
{
    use SnapshotHasher;

    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();
        if ($action === null) {
            return null;
        }

        $message = $this->getMessage($action);

        $params = $context->get('params');
        $blockID = $params['blockId'];
        $block = BaseElement::get()->byID($blockID);

        // Ensure the block is gone
        if ($block) {
            return null;
        }
        $archivedBlock = SnapshotPublishable::get_at_last_snapshot(BaseElement::class, $blockID);
        if (!$archivedBlock) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($archivedBlock);
    }
}
