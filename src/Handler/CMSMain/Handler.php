<?php

namespace SilverStripe\Snapshots\Handler\CMSMain;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;

/**
 * Event hook for @see CMSMain
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
        $action = $context->getAction();

        if ($action === null) {
            return null;
        }

        /** @var HTTPResponse $result */
        $result = $context->get('result');

        if (!$result instanceof HTTPResponse) {
            return null;
        }

        if ($result->getStatusCode() !== 200) {
            return null;
        }

        $className = $context->get('treeClass');
        $id = (int) $context->get('id');

        if (!$id) {
            return null;
        }

        /** @var SiteTree $page */
        $page = DataObject::get_by_id($className, $id);

        if ($page === null) {
            return null;
        }

        $message = $this->getMessage($action);

        return Snapshot::singleton()->createSnapshotEvent($message, [$page]);
    }
}
