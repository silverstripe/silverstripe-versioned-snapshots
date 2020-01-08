<?php

namespace SilverStripe\Snapshots\Listener;

use GraphQL\Type\Schema;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
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
class GraphQLMiddlewareListener extends Extension
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
        Dispatcher::singleton()->trigger('graphqlMiddleware', new Context([
            'schema' => $schema,
            'query' => $query,
            'context' => $context,
            'params' => $params,
        ]));
    }

}
