<?php


namespace SilverStripe\Snapshots\Handler\GridField\Alteration;

use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class Handler extends HandlerAbstract
{
    /**
     * @param EventContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContext $context): ?Snapshot
    {
        $action = $context->getAction();
        if ($action === null) {
            return null;
        }

        $message = $this->getMessage($action);
        /* @var Form $form */
        $form = $context->get('gridField')->getForm();

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
