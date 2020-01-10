<?php


namespace SilverStripe\Snapshots\Handler\GraphQL;


use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\GraphQL\GraphQLMiddlewareContext;
use SilverStripe\Snapshots\Listener\ListenerContext;
use SilverStripe\Snapshots\Snapshot;

class GenericHandler extends HandlerAbstract
{
    /**
     * @param ListenerContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(ListenerContext $context): ?Snapshot
    {
        /* @var GraphQLMiddlewareContext $context */
        $action = $context->getAction();
        $message = $this->getMessage($action);
        $page = $this->getPageFromReferrer();

        if ($page === null) {
            return null;
        }

        return Snapshot::singleton()->createSnapshotFromAction($page, null, $message);
    }


}
