<?php

namespace SilverStripe\Snapshots\Handler\CsvBulkLoader;

use InvalidArgumentException;
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
        $obj = $context->get('record');

        if (!$obj) {
            throw new InvalidArgumentException('Requires "record" in context');
        }

        // Create an individual snapshot for each object to ensure they're all captured.
        // Unlike a recursive publish, with imports we rely on all objects noting the action in their history.
        // The disadvantage of this approach is that the origin of the modification (a CSV import) isn't recorded.
        return Snapshot::singleton()->createSnapshot($obj);
    }
}
