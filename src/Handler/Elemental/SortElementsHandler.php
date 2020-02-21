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
        $page = $this->getPageFromReferrer();
        if (!$page) {
            return null;
        }

        $params = $context->get('params');

        /* @var SnapshotPublishable $block */
        $block = BaseElement::get()->byID($params['blockId']);
        if (!$block) {
            return null;
        }

        $area = $block->Parent();
        $currentSorts = $area->Elements()->sort('Sort', 'ASC')->column('ID');

        // Look at the last snapshot of the area, because it should be part of any change to the blocks.
        $previousSorts = $area->atPreviousSnapshot(function ($date) use ($block, $area) {
            if (!$date) {
                return [];
            }
            $oldArea = DataObject::get_by_id(ElementalArea::class, $area->ID);
            return $oldArea->Elements()->sort('Sort', 'ASC')->column('ID');
        });

        if (empty($previousSorts)) {
            return null;
        }

        $affectedBlocks = [];
        foreach ($currentSorts as $newPosition => $id) {
            $oldPosition = array_search($id, $previousSorts);
            if ($oldPosition === false || $newPosition !== $oldPosition) {
                $affectedBlocks[] = $id;
            }
        }
        if (empty($affectedBlocks)) {
            return null;
        }
        $message = _t(__CLASS__ . '.REORDER_BLOCKS', 'Reordered blocks');
        $snapshot = Snapshot::singleton()->createSnapshotEvent($message);
        $snapshot->addObject($page);
        $elements = BaseElement::get()->byIDs($affectedBlocks);

        // Add each affected block to the snapshot
        foreach ($elements as $e) {
            $snapshot->addObject($e);
        }

        // Intermediary objects
        foreach ($block->getIntermediaryObjects() as $obj) {
            $snapshot->addObject($obj);
        }

        return $snapshot;
    }
}
