<?php

namespace SilverStripe\Snapshots\Listener\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Listener\CurrentPage;

/**
 * Class GenericAction
 *
 * Snapshot action listener for GraphQL actions via generic CRUD API
 *
 * @property Create|Delete|Update|$this $owner
 * @package SilverStripe\Snapshots\Listener\GraphQL
 */
class GenericAction extends Extension
{

    use CurrentPage;

    const TYPE_CREATE = 'create';
    const TYPE_DELETE = 'delete';
    const TYPE_UPDATE = 'update';
    const ACTION_PREFIX = 'graphql_crud_';

    /**
     * Extension point in @see Create::resolve
     * Extension point in @see Delete::resolve
     * Extension point in @see Update::resolve
     * Graph QL action via generic CRUD API
     *
     * @param mixed $recordOrList
     * @param array $args
     * @param mixed $context
     * @param mixed $info
     * @throws ValidationException
     */
    public function afterMutation($recordOrList, array $args, $context, ResolveInfo $info): void
    {
        $snapshot = Snapshot::singleton();

        if (!$snapshot->isActionTriggerActive()) {
            return;
        }

        $type = $this->getActionType();

        if (!$type) {
            return;
        }

        $action = static::ACTION_PREFIX . $type;
        $message = $snapshot->getActionMessage($action);

        if ($message === null) {
            return;
        }

        $controller = Controller::curr();

        if (!$controller) {
            return;
        }

        $request = $controller->getRequest();

        if (!$request) {
            return;
        }

        $url = $request->getHeader('referer');
        $url = parse_url($url, PHP_URL_PATH);
        $url = ltrim($url, '/');
        $page = $this->getCurrentPageFromRequestUrl($url);

        if ($page === null) {
            return;
        }

        // attempt to create a custom snapshot first
        $customSnapshot = $snapshot->graphQLGenericActionSnapshot(
            $page,
            $action,
            $message,
            $recordOrList,
            $args,
            $context,
            $info
        );

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, null, $message);
    }

    private function getActionType(): ?string
    {
        $owner = $this->owner;

        if ($owner instanceof Create) {
            return static::TYPE_CREATE;
        }

        if ($owner instanceof Delete) {
            return static::TYPE_DELETE;
        }

        if ($owner instanceof Update) {
            return static::TYPE_UPDATE;
        }

        return null;
    }
}
