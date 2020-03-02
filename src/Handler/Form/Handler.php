<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Versioned\Versioned;

class Handler extends HandlerAbstract
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();
        if ($action === null) {
            return null;
        }

        $record = $this->getRecordFromContext($context);

        if ($record === null || !$record->hasExtension(Versioned::class)) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($record);
    }

    /**
     * @param EventContextInterface $context
     * @return DataObject|null
     */
    protected function getPageFromContext(EventContextInterface $context): ?DataObject
    {
        $page = $context->get('page');
        if ($page) {
            return $page;
        }

        /* @var HTTPRequest $request */
        $request = $context->get('request');

        if (!$request || !$request instanceof HTTPRequest) {
            return null;
        }

        $url = $request->getURL();
        return $this->getCurrentPageFromRequestUrl($url);
    }

    /**
     * @param EventContextInterface $context
     * @return DataObject|null
     */
    protected function getRecordFromContext(EventContextInterface $context): ?DataObject
    {
        /** @var Form $form */
        $form = $context->get('form');

        $record = $form->getRecord();
        if (!$record) {
            return null;
        }

        return DataObject::get_by_id($record->baseClass(), $record->ID);
    }
}
