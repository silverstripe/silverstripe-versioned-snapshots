<?php

namespace SilverStripe\Snapshots\Listener\GraphQL\Mutation;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\ORM\SS_List;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Listener\EventContext;

/**
 * Class GenericAction
 *
 * Snapshot action listener for GraphQL actions via generic CRUD API
 *
 * @property Create|Delete|Update|$this $owner
 */
class Listener extends Extension
{

    const TYPE_CREATE = 'create';
    const TYPE_DELETE = 'delete';
    const TYPE_UPDATE = 'update';

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
            new EventContext(
                $this->getActionFromScaffolder($this->owner),
                [
                    'list' => $recordOrList instanceof SS_List ? $recordOrList : null,
                    'record' => !$recordOrList instanceof SS_List ? $recordOrList : null,
                    'args' => $args,
                    'context' => $context,
                    'info' => $info,
                ]
            )
        );
    }

    private function getActionFromScaffolder(OperationResolver $scaffolder): ?string
    {
        if ($scaffolder instanceof Create) {
            return static::TYPE_CREATE;
        }

        if ($scaffolder instanceof Delete) {
            return static::TYPE_DELETE;
        }

        if ($scaffolder instanceof Update) {
            return static::TYPE_UPDATE;
        }

        return null;
    }
}
