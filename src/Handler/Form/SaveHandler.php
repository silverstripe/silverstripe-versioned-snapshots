<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class SaveHandler extends FormSubmissionHandler
{
    /**
     * @param EventContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContext $context): ?Snapshot
    {

        $page = $this->getPage($context);
        if (!$page || !$page->isModifiedOnDraft()) {
            return null;
        }

        return parent::createSnapshot($context);
    }
}
