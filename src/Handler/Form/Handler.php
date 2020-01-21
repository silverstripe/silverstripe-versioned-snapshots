<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;

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

        return Snapshot::singleton()->createSnapshotFromAction($page, $record, $message);
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
}
