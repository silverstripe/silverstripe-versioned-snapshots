<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\ElementalArea;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Snapshot;

/**
 * Event hook for @see BaseElement
 */
class CreateElementHandler extends ElementalHandlerAbstract
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

        $areaID = $this->getElementalAreaID($context);

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
