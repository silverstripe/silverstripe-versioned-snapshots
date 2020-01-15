<?php


namespace SilverStripe\Snapshots\Handler\GraphQL\Middleware;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
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

        $message = $this->getMessage($action);
        $page = $this->getPageFromReferrer();

        if ($page === null) {
            return null;
        }

        return Snapshot::singleton()->createSnapshotFromAction($page, null, $message);
    }
}
