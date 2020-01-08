<?php

namespace SilverStripe\Snapshots\Listener;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
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
     * @throws ValidationException
     */
    public function afterMutation($recordOrList, array $args, $context, ResolveInfo $info): void
    {
        Dispatcher::singleton()->trigger('graphqlMutation', new Context([
            'list' => $recordOrList instanceof SS_List ? $recordOrList : null,
            'record' => !$recordOrList instanceof SS_List ? $recordOrList : null,
            'args' => $arge,
            'context' => $context,
            'resolveInfo' => $info,
            'mutation' => $this->owner,
        ]));
    }
}
