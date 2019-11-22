<?php

namespace SilverStripe\Snapshots\Listener\GraphQL;

use GraphQL\Type\Schema;
use Page;
use SilverStripe\Core\Extension;

/**
 * Class CustomActionListenerAbstract
 *
 * @package SilverStripe\Snapshots\Listener\GraphQL
 */
abstract class CustomActionListenerAbstract extends Extension
{
    /**
     * Extension point in @see Snapshot::graphQLCustomActionSnapshot()
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param Schema $schema
     * @param string $query
     * @param array $context
     * @param array $params
     * @return bool
     */
    public function overrideGraphQLCustomActionSnapshot(
        Page $page,
        string $action,
        string $message,
        Schema $schema,
        string $query,
        array $context,
        array $params
    ): bool {
        if ($action !== $this->getActionName()) {
            return false;
        }

        return $this->processAction($page, $action, $message, $schema, $query, $context, $params);
    }

    abstract protected function getActionName(): string;

    abstract protected function processAction(
        Page $page,
        string $action,
        string $message,
        Schema $schema,
        string $query,
        array $context,
        array $params
    ): bool;
}
