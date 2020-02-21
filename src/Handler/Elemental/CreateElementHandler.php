<?php


namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\SnapshotPublishable;

class CreateElementHandler extends Handler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();
        if ($action === null) {
            return null;
        }

        $message = $this->getMessage($action);
        $params = $context->get('params');
        $className = $params['className'];
        $areaID = $params['elementalAreaID'];
        $area = ElementalArea::get()->byID($areaID);

        if (!$area) {
            return null;
        }
        $justCreated = $area->Elements()
            ->filter('ClassName', $className)
            ->sort('Created', 'DESC')
            ->first();

        if (!$justCreated) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($justCreated);
    }
}
