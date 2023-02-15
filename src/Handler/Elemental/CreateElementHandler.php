<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;

class CreateElementHandler extends Handler
{
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

        $areaID = $params['elementalAreaID'] ?? null;

        if (!$areaID) {
            return null;
        }

        $area = ElementalArea::get()->byID($areaID);

        if (!$area) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($area);
    }
}
