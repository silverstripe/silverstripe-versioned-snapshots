<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotPublishable;

class ArchiveElementHandler extends Handler
{

    use SnapshotHasher;

    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();

        if ($action === null) {
            return null;
        }

        // GraphQL 4 ?? GraphQL 3
        $params = $context->get('variables') ?? $context->get('params');

        if (!$params) {
            return null;
        }

        $blockID = $params['blockId'] ?? null;

        if (!$blockID) {
            return null;
        }

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
