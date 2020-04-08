<?php


namespace SilverStripe\Snapshots\Handler\GraphQL\Middleware;

use GraphQL\Executor\ExecutionResult;
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

        if (!preg_match('/^rollback/', $action)) {
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

        $result = $context->get('result');
        if (!$result instanceof ExecutionResult) {
            return null;
        }
        if (!empty($result->errors)) {
            return null;
        }
        $data = $result->data;
        $className = $data[$action]['ClassName'] ?? null;
        if (!$className) {
            return null;
        }

        /* @var DataObject|SnapshotPublishable $obj */
        $obj = DataObject::get_by_id($className, $id);
        if (!$obj) {
            return null;
        }
        $wasVersion = $obj->getPreviousSnapshotVersion();
        $nowVersion = Versioned::get_version($className, $id, $toVersion);

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

        return $snapshot->addObject($obj);
    }
}
