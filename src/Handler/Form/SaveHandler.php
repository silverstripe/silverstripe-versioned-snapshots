<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;

class SaveHandler extends Handler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {

        $page = $this->getPage($context);
        if (!$page || !$page->isModifiedOnDraft()) {
            return null;
        }

        return parent::createSnapshot($context);
    }
}
