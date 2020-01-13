<?php


namespace SilverStripe\Snapshots\Handler\CMSMain;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObject;
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

        /* @var HTTPResponse $result */
        $result = $context->get('result');
        if (!$result instanceof HTTPResponse) {
            return null;
        }
        if ((int) $result->getStatusCode() !== 200) {
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

        return Snapshot::singleton()->createSnapshotFromAction($page, null, $message);
    }
}
