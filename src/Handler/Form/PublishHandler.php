<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Snapshots\Snapshot;

class PublishHandler extends Handler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $snapshot = parent::createSnapshot($context);
        if (!$snapshot) {
            return null;
        }

        // mark publish actions as WasPublished - the status flags rely on this being set correctly
        /* @var Form $form */
        $form = $context->get('form');
        if ($form->getName() === 'EditForm') {
            foreach ($snapshot->Items() as $item) {
                $item->WasPublished = true;
                $item->write();
            }
        }

        return $snapshot;
    }
}
