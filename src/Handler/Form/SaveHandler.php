<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Versioned\Versioned;

class SaveHandler extends Handler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        /** @var Form $form */
        $form = $context->get('form');
        if ($form === null) {
            return parent::createSnapshot($context);
        }

        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();

        if ($record === null) {
            return parent::createSnapshot($context);
        }

        if (!$record->hasExtension(Versioned::class)) {
            return parent::createSnapshot($context);
        }

        if ($record instanceof SnapshotEvent) {
            return parent::createSnapshot($context);
        }

        if ($record->isModifiedOnDraft()) {
            return parent::createSnapshot($context);
        }

        return null;
    }
}
