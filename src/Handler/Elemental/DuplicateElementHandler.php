<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;

/**
 * Event hook for @see BaseElement
 * Handles duplicate on blocks
 */
class DuplicateElementHandler extends ElementalHandlerAbstract
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

        $blockID = $this->getBlockID($context);

        if (!$blockID) {
            return null;
        }

        /** @var BaseElement|SnapshotPublishable $block */
        $block = BaseElement::get()->byID($blockID);

        if (!$block) {
            return null;
        }

        $message = _t(
            DuplicateElementHandler::class . '.DUPLICATE_BLOCK',
            'Duplicated block "{title}"',
            ['title' => $block->Title]
        );
        $event = Snapshot::singleton()->createSnapshotEvent($message);
        $event->addOwnershipChain($block);

        $duplicate = $this->getDuplicatedBlock($block);

        if ($duplicate) {
            $event->addOwnershipChain($duplicate);
        }

        return $event;
    }

    /**
     * The duplicate action puts the new block directly after the source block,
     * so the new block is the next block in the sort order of the elemental area
     */
    protected function getDuplicatedBlock(BaseElement $block): ?BaseElement
    {
        $area = $block->Parent();

        if (!$area instanceof ElementalArea || !$area->exists()) {
            return null;
        }

        $blockIDs = $area
            ->Elements()
            ->sort('Sort', 'ASC')
            ->column('ID');

        $position = array_search($block->ID, $blockIDs);

        if ($position === false) {
            return null;
        }

        $nextPosition = $position + 1;

        if (!array_key_exists($nextPosition, $blockIDs)) {
            return null;
        }

        return BaseElement::get()->byID($blockIDs[$nextPosition]);
    }
}
