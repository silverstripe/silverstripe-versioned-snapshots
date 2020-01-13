<?php

namespace SilverStripe\Snapshots\Listener\GraphQL\Middleware;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Listener\EventContext;

/**
 * Class CustomAction
 *
 * Snapshot action listener for GraphQL custom actions
 *
 * @property Manager|$this $owner
 */
class Listener extends Extension
{
    /**
     * Extension point in @see Manager::callMiddleware
     * Graph QL custom action
     *
     * @param Schema $schema
     * @param string $query
     * @param array $context
     * @param array|null $params
     * @throws SyntaxError
     */
    public function onAfterCallMiddleware(Schema $schema, string $query, array $context, $params): void
    {
        Dispatcher::singleton()->trigger(
            'graphqlOperation',
            new EventContext(
                $this->getActionFromQuery($query),
                [
                    'schema' => $schema,
                    'context' => $context,
                    'params' => $params,
                ]
            )
        );
    }

    /**
     * @param string|null $query
     * @return string
     * @throws SyntaxError
     */
    private function getActionFromQuery(?string $query = null): string
    {
        $document = Parser::parse(new Source($query ?: 'GraphQL'));
        $defs = $document->definitions;
        foreach ($defs as $statement) {
            $options = [
                NodeKind::OPERATION_DEFINITION,
                NodeKind::OPERATION_TYPE_DEFINITION
            ];
            if (!in_array($statement->kind, $options, true)) {
                continue;
            }
            if (in_array($statement->operation, [Manager::MUTATION_ROOT, Manager::QUERY_ROOT])) {
                $selectionSet = $statement->selectionSet;
                if ($selectionSet) {
                    $selections = $selectionSet->selections;
                    if (!empty($selections)) {
                        $firstField = $selections[0];

                        return $firstField->name->value;
                    }
                }
                return $statement->operation;
            }
        }
        return 'graphql';
    }
}
