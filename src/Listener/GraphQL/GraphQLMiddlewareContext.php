<?php


namespace SilverStripe\Snapshots\Listener\GraphQL;


use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Snapshots\Listener\EventContext;

class GraphQLMiddlewareContext extends EventContext
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var Schema|null
     */
    private $schema;

    /**
     * @var array
     */
    private $graphqlContext = [];

    /**
     * @var array
     */
    private $params = [];

    /**
     * GraphQLMiddlewareContext constructor.
     * @param string $query
     * @param Schema|null $schema
     * @param array $graphqlContext
     * @param array|null $params
     */
    public function __construct(string $query, ?Schema $schema = null, array $graphqlContext = [], ?array $params = [])
    {
        $this->query = $query;
        $this->schema = $schema;
        $this->graphqlContext = $graphqlContext;
        $this->params = $params;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return Schema|null
     */
    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    /**
     * @return array
     */
    public function getGraphqlContext(): array
    {
        return $this->graphqlContext;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        $document = Parser::parse(new Source($this->getQuery() ?: 'GraphQL'));
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
