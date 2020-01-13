<?php


namespace SilverStripe\Snapshots\Handler\GraphQL\Mutation;

use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class Handler extends HandlerAbstract
{
    const ACTION_PREFIX = 'graphql_crud_';

    /**
     * @param EventContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContext $context): ?Snapshot
    {
        $type = $context->getAction();
        if ($type === null) {
            return null;
        }

        $action = static::ACTION_PREFIX . $type;
        $message = $this->getMessage($action);
        $page = $this->getPageFromReferrer();

        if ($page === null) {
            return null;
        }

        return Snapshot::singleton()->createSnapshotFromAction($page, null, $message);
    }
}
