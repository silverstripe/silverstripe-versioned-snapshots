<?php


namespace SilverStripe\Snapshots\Handler\GraphQL\Mutation;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;

class Handler extends HandlerAbstract
{
    const ACTION_PREFIX = 'graphql_crud_';

    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $type = $context->getAction();
        if ($type === null) {
            return null;
        }

        $page = $this->getPageContextProvider()->getPageFromReferrer();

        if ($page === null) {
            return null;
        }

        return Snapshot::singleton()->createSnapshot($page);
    }
}
