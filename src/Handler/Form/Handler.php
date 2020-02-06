<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\SnapshotPublishable;
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

        $page = $this->getPage($context);
        $record = null;
        if ($form = $context->get('form')) {
            $record = $form->getRecord();
        }

        if ($page === null || $record === null) {
            return null;
        }

        $message = $this->getMessage($action);

        /* @var SnapshotPublishable|DataObject|Versioned $record */
        list ($extraObjects, $newMessage) = $record->createOwnershipGraph($page);

        $snapshot = Snapshot::singleton()->createSnapshotFromAction($page, $record, $newMessage ?: $message, $extraObjects);

        if ($snapshot && !empty($implicitObjects)) {
            $snapshot->applyImplicitObjects($implicitObjects);
        }

        $record->reconcileOwnershipChanges($this->getPreviousVersion($record));

        return $snapshot;
    }

    /**
     * @param EventContextInterface $context
     * @return DataObject|null
     */
    protected function getPage(EventContextInterface $context): ?DataObject
    {
        $page = $context->get('page');
        if ($page) {
            return $page;
        }

        /* @var HTTPRequest $request */
        $request = $context->get('request');
        $url = $request->getURL();
        return $this->getCurrentPageFromRequestUrl($url);
    }

    /**
     * @param DataObject $record
     * @param null $version
     * @return DataObject|null
     */
    protected function getPreviousVersion(DataObject $record, $version = null): ?DataObject
    {
        $previous = null;
        if ($record->Version == 1) {
            $previous = Injector::inst()->create(get_class($record));
        } else {
            if ($version === null) {
                $version = $record->Version - 1;
            }

            $previous = $record->getAtVersion($version);
        }

        return $previous;
    }
}
