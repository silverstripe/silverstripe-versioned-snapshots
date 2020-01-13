<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\Forms\Form;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class PublishHandler extends FormSubmissionHandler
{
    protected function createSnapshot(EventContext $context): ?Snapshot
    {
        $snapshot = parent::createSnapshot($context);
        if ($snapshot) {
            // mark publish actions as WasPublished - the status flags rely on this being set correctly
            /* @var Form $form */
            $form = $context->get('form');
            if ($form->getName() === 'EditForm') {
                foreach ($snapshot->Items() as $item) {
                    $item->WasPublished = true;
                    $item->write();
                }
            }
        }

        return $snapshot;
    }
}
