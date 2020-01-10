<?php

namespace SilverStripe\Snapshots\Listener\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\ORM\SS_List;
use SilverStripe\Snapshots\Dispatch\Dispatcher;

/**
 * Class GenericAction
 *
 * Snapshot action listener for GraphQL actions via generic CRUD API
 *
 * @property Create|Delete|Update|$this $owner
 */
class GraphQLMutationListener extends Extension
{
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
     */
    public function afterMutation($recordOrList, array $args, $context, ResolveInfo $info): void
    {
        Dispatcher::singleton()->trigger(
            'graphqlMutation',
            new GraphQLMutationContext(
                $this->owner,
                $recordOrList instanceof SS_List ? $recordOrList : null,
                !$recordOrList instanceof SS_List ? $recordOrList : null,
                $args,
                $context,
                $info
            )
        );
    }
}
