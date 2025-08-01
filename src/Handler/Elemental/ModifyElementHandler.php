<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;

/**
 * Event hook for @see BaseElement
 * Handles reordering, publish, unpublish, archive, create on blocks
 */
class ModifyElementHandler extends Handler
{

    use SnapshotHasher;

    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     * @throws NotFoundExceptionInterface
     */
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

        if (!$block) {
            return null;
        }

        $snapshot = Snapshot::singleton()->createSnapshot($block);

        if (!$snapshot) {
            return null;
        }

        /** @var SnapshotItem $item */
        foreach ($snapshot->Items() as $item) {
            if (!$this->hashSnapshotCompare($item->getItem(), $block)) {
                continue;
            }

            // If it's the origin item, set published state.
            $item->WasPublished = $action === 'PublishBlock';
            $item->WasUnpublished = $action === 'UnpublishBlock';
        }

        return $snapshot;
    }
}
