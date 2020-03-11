<?php


namespace SilverStripe\Snapshots\Handler\Elemental;


use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;

class SortElementsHandler extends Handler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();
        if ($action === null) {
            return null;
        }
        $params = $context->get('params');
        if (!$params) {
            return null;
        }
        $blockID = $params['blockId'] ?? null;
        if (!$blockID) {
            return null;
        }
        /* @var SnapshotPublishable $block */
        $block = BaseElement::get()->byID($blockID);
        if (!$block) {
            return null;
        }

        $area = $block->Parent();

        return Snapshot::singleton()->createSnapshotEvent(
            _t(__CLASS__ . '.REORDER_BLOCKS', 'Reordered blocks'),
        )->addOwnershipChain($area);
    }
}
