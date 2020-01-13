<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class FormSubmissionHandler extends HandlerAbstract
{
    /**
     * @param EventContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContext $context): ?Snapshot
    {
        $action = $context->getAction();
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
     * @param EventContext $context
     * @return SiteTree|null
     */
    protected function getPage(EventContext $context): ?SiteTree
    {
        /* @var HTTPRequest $request */
        $request = $context->get('request');
        $url = $request->getURL();
        return $this->getCurrentPageFromRequestUrl($url);
    }
}
