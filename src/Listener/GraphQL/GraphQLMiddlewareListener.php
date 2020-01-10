<?php

namespace SilverStripe\Snapshots\Listener\GraphQL;

use GraphQL\Type\Schema;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Snapshots\Dispatch\Dispatcher;

/**
 * Class CustomAction
 *
 * Snapshot action listener for GraphQL custom actions
 *
 * @property Manager|$this $owner
 */
class GraphQLMiddlewareListener extends Extension
{
    /**
     * Extension point in @see Manager::callMiddleware
     * Graph QL custom action
     *
     * @param Schema $schema
     * @param string $query
     * @param array $context
     * @param array|null $params
     */
    public function onAfterCallMiddleware(Schema $schema, string $query, array $context, $params): void
    {
        Dispatcher::singleton()->trigger(
            'graphqlOperation',
            new GraphQLMiddlewareContext($query, $schema, $context, $params)
        );
    }

}
