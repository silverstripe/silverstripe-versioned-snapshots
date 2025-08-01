<?php

namespace SilverStripe\Snapshots\Handler\Form;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Versioned\Versioned;

/**
 * Event hook for @see Form
 */
class Handler extends HandlerAbstract
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

        $record = $this->getRecordFromContext($context);

        if ($record === null || !$record->hasExtension(Versioned::class)) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($record);
    }

    protected function getPageFromContext(EventContextInterface $context): ?DataObject
    {
        $page = $context->get('page');

        if ($page) {
            return $page;
        }

        /** @var HTTPRequest $request */
        $request = $context->get('request');

        if (!$request instanceof HTTPRequest) {
            return null;
        }

        $url = $request->getURL();

        return $this
            ->getPageContextProvider()
            ->getCurrentPageFromRequestUrl($url);
    }

    protected function getRecordFromContext(EventContextInterface $context): ?DataObject
    {
        /** @var DataObject $record */
        $record = $context->get('record');

        if ($record) {
            return $record;
        }

        /** @var Form $form */
        $form = $context->get('form');

        /** @var DataObject $record */
        $record = $form->getRecord();

        if (!$record) {
            return null;
        }

        $reFetched = DataObject::get_by_id($record->baseClass(), $record->ID);

        // If the record was deleted, return the version still linked to the form
        if (!$reFetched && $record->hasExtension(Versioned::class)) {
            return $record;
        }

        return $reFetched;
    }
}
