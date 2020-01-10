<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\Snapshots\Listener\Form\FormContext;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class PublishHandler extends FormSubmissionHandler
{
    protected function createSnapshot(EventContext $context): ?Snapshot
    {
        /* @var FormContext $context */
        $snapshot = parent::createSnapshot($context);
        if ($snapshot) {
            // mark publish actions as WasPublished - the status flags rely on this being set correctly
            if ($context->getForm()->getName() === 'EditForm') {
                foreach ($snapshot->Items() as $item) {
                    $item->WasPublished = true;
                    $item->write();
                }
            }
        }

        return $snapshot;
    }
}
