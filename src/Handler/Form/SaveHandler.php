<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Listener\Form\FormContext;
use SilverStripe\Snapshots\Listener\ListenerContext;
use SilverStripe\Snapshots\Snapshot;

class SaveHandler extends FormSubmissionHandler
{
    /**
     * @param ListenerContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(ListenerContext $context): ?Snapshot
    {
        /* @var FormContext $context */
        $page = $this->getPage($context);
        if (!$page || !$page->isModifiedOnDraft()) {
            return null;
        }

        return parent::createSnapshot($context);
    }
}
