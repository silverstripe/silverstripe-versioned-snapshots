<?php


namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\ORM\ValidationException;

/**
 * Handles save, publish on individual blocks
 */
class CMSActionsHandler extends HandlerAbstract
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();
        if (!$action) {
            return null;
        }

        $request = $context->get('request');
        if (!$request) {
            return null;
        }

        $id = $request->param('ID');
        if (!$id) {
            return null;
        }

        $block = DataObject::get_by_id(BaseElement::class, $id);
        if (!$block) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($block);
    }
}
