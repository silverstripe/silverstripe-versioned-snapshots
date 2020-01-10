<?php


namespace SilverStripe\Snapshots\Handler\GridField;

use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\GridField\GridFieldContext;
use SilverStripe\Snapshots\Listener\ListenerContext;
use SilverStripe\Snapshots\Snapshot;

class URLActionHandler extends HandlerAbstract
{
    /**
     * @param ListenerContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(ListenerContext $context): ?Snapshot
    {
        /* @var GridFieldContext $context */
        $action = $context->getAction();
        $message = $this->getMessage($action);
        $form = $context->getGridField()->getForm();

        if (!$form) {
            return null;
        }

        $record = $form->getRecord();

        if (!$record) {
            return null;
        }

        $page = $this->getCurrentPageFromController($form);

        if ($page === null) {
            return null;
        }

        return Snapshot::singleton()->createSnapshotFromAction($page, $record, $message);
    }
}
