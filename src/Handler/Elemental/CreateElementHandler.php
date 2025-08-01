<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\ElementalArea;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Snapshot;

/**
 * Event hook for @see BaseElement
 */
class CreateElementHandler extends Handler
{
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
