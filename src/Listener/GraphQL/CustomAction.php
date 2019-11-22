<?php

namespace SilverStripe\Snapshots\Listener\GraphQL;

use GraphQL\Type\Schema;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Listener\CurrentPage;

/**
 * Class CustomAction
 *
 * Snapshot action listener for GraphQL custom actions
 *
 * @property Manager|$this $owner
 * @package SilverStripe\Snapshots\Listener\GraphQL
 */
class CustomAction extends Extension
{

    use CurrentPage;

    /**
     * Extension point in @see Manager::callMiddleware
     * Graph QL custom action
     *
     * @param Schema $schema
     * @param string $query
     * @param array $context
     * @param array $params
     * @throws ValidationException
     */
    public function onAfterCallMiddleware(Schema $schema, string $query, array $context, array $params): void
    {
        $snapshot = Snapshot::singleton();

        if (!$snapshot->isActionTriggerActive()) {
            return;
        }

        $action = $this->getActionType($query);

        if (!$action) {
            return;
        }

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
        $customSnapshot = $snapshot->graphQLCustomActionSnapshot(
            $page,
            $action,
            $message,
            $schema,
            $query,
            $context,
            $params
        );

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, null, $message);
    }

    /**
     * Extract action type from query
     *
     * @param string $query
     * @return string|null
     */
    private function getActionType(string $query): ?string
    {
        $action = explode('(', $query);

        if (count($action) === 0) {
            return null;
        }

        $action = array_shift($action);

        if (!$action) {
            return null;
        }

        $action = str_replace(' ', '_', $action);

        return $action;
    }
}
