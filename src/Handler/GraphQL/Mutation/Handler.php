<?php

namespace SilverStripe\Snapshots\Handler\GraphQL\Mutation;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;

/**
 * Event hook for GraphQL operations
 *
 * @deprecated GraphQL no longer officially supported
 */
class Handler extends HandlerAbstract
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     * @throws NotFoundExceptionInterface
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
