<?php


namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;

/**
 * Handles reordering, publish, unpublish, archive, create on blocks
 */
class ModifyElementHandler extends Handler
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

        if (!$block) {
            return null;
        }

        $snapshot = Snapshot::singleton()->createSnapshot($block);

        foreach ($snapshot->Items() as $item) {
            // If it's the origin item, set published state.
            if (static::hashSnapshotCompare($item->getItem(), $block)) {
                $item->WasPublished = $action === 'PublishBlock';
                $item->WasUnpublished = $action === 'UnpublishBlock';
            }
        }

        return $snapshot;
    }
}
