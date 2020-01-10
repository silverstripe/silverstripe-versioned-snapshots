<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\Form\FormContext;
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
        /* @var FormContext $context */
        $action = $context->getAction();
        $page = $this->getPage($context);
        $record = $context->getForm()->getRecord();

        if ($page === null || $record === null) {
            return null;
        }

        $message = $this->getMessage($action);

        return Snapshot::singleton()->createSnapshotFromAction($page, $record, $message);
    }

    /**
     * @param FormContext $context
     * @return SiteTree|null
     */
    protected function getPage(FormContext $context): ?SiteTree
    {
        $url = $context->getRequest()->getURL();
        return $this->getCurrentPageFromRequestUrl($url);
    }
}
