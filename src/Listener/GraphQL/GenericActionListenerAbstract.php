<?php

namespace SilverStripe\Snapshots\Listener\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use Page;
use SilverStripe\Core\Extension;

/**
 * Class GenericHandlerAbstract
 *
 * @package SilverStripe\Snapshots\Listener\GraphQL
 */
abstract class GenericActionListenerAbstract extends Extension
{
    /**
     * Extension point in @see Snapshot::graphQLGenericActionSnapshot()
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param $recordOrList
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return bool
     */
    public function overrideGraphQLGenericActionSnapshot(
        Page $page,
        string $action,
        string $message,
        $recordOrList,
        array $args,
        $context,
        ResolveInfo $info
    ): bool {
        if ($action !== $this->getActionName()) {
            return false;
        }

        return $this->processAction($page, $action, $message, $recordOrList, $args, $context, $info);
    }

    abstract protected function getActionName(): string;

    abstract protected function processAction(
        Page $page,
        string $action,
        string $message,
        $recordOrList,
        array $args,
        $context,
        ResolveInfo $info
    ): bool;
}
