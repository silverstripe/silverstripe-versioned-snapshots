<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;

/**
 * Event hook for @see BaseElement
 * Handles reordering, publish, unpublish, archive, create on blocks
 */
class ModifyElementHandler extends ElementalHandlerAbstract
{

    use SnapshotHasher;

    /**
     * GraphQL mutation (elemental 5) and REST endpoint (elemental 6) names
     */
    private const array PUBLISH_ACTIONS = [
        'PublishBlock',
        'apiPublish'
    ];

    private const array UNPUBLISH_ACTIONS = [
        'UnpublishBlock',
        'apiUnpublish'
    ];

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

        $blockID = $this->getBlockID($context);

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
            $item->WasPublished = in_array($action, ModifyElementHandler::PUBLISH_ACTIONS, true);
            $item->WasUnpublished = in_array($action, ModifyElementHandler::UNPUBLISH_ACTIONS, true);
        }

        return $snapshot;
    }
}
