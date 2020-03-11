<?php


namespace SilverStripe\Snapshots\Handler\GraphQL\Middleware;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Versioned\Versioned;

class RollbackHandler extends HandlerAbstract
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
        $params = $context->get('params');
        if (!$params) {
            return null;
        }

        $id = $params['id'] ?? null;
        $toVersion = $params['toVersion'] ?? null;

        if (!$id || !$toVersion) {
            return null;
        }
        /* @var SiteTree|SnapshotPublishable $page */
        $page = DataObject::get_by_id(SiteTree::class, $id);
        if (!$page) {
            return null;
        }
        $wasVersion = $page->getPreviousSnapshotVersion();
        $nowVersion = Versioned::get_version(SiteTree::class, $id, $toVersion);

        if (!$wasVersion || !$nowVersion) {
            return null;
        }

        $snapshot = Snapshot::singleton()->createSnapshotEvent(
            _t(
                __CLASS__ . '.ROLLBACK_TO_VERSION',
                'Rolled back to version {version}',
                [
                    'version' => $toVersion,
                ]
            )
        );

        return $snapshot->addObject($page);
    }
}
