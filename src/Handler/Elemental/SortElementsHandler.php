<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;

/**
 * Event hook for @see BaseElement
 */
class SortElementsHandler extends Handler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
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
        $message = _t(SortElementsHandler::class . '.REORDER_BLOCKS', 'Reordered blocks');
        $event = Snapshot::singleton()->createSnapshotEvent($message);
        $event->addOwnershipChain($area);

        return $event;
    }
}
