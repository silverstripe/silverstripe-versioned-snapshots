<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use Exception;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;

class SortElementsHandler extends Handler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     * @throws Exception
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

        /** @var BaseElement|SnapshotPublishable $block */
        $block = BaseElement::get()->byID($blockID);

        if (!$block) {
            return null;
        }

        $area = $block->Parent();

        return Snapshot::singleton()
            ->createSnapshotEvent(_t(self::class . '.REORDER_BLOCKS', 'Reordered blocks'))
            ->addOwnershipChain($area);
    }
}
