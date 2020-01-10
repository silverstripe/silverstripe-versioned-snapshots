<?php


namespace SilverStripe\Snapshots\Handler\GraphQL;


use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\GraphQL\GraphQLMutationContext;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

class MutationHandler extends HandlerAbstract
{
    const ACTION_PREFIX = 'graphql_crud_';

    /**
     * @param EventContext $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContext $context): ?Snapshot
    {
        /* @var GraphQLMutationContext $context */
        $type = $context->getAction();
        $action = static::ACTION_PREFIX . $type;
        $message = $this->getMessage($action);
        $page = $this->getPageFromReferrer();

        if ($page === null) {
            return null;
        }

        return Snapshot::singleton()->createSnapshotFromAction($page, null, $message);
    }


}
