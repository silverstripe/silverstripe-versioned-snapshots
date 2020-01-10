<?php


namespace SilverStripe\Snapshots\Listener\GraphQL;


use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\ORM\SS_List;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\View\ViewableData;

class GraphQLMutationContext extends EventContext
{


    const TYPE_CREATE = 'create';
    const TYPE_DELETE = 'delete';
    const TYPE_UPDATE = 'update';


    /**
     * @var MutationScaffolder
     */
    private $mutation;

    /**
     * @var SS_List|null
     */
    private $list;

    /**
     * @var ViewableData|null
     */
    private $record;

    /**
     * @var array
     */
    private $args = [];

    /**
     * @var array
     */
    private $graphqlContext = [];

    /**
     * @var ResolveInfo|null
     */
    private $info;

    /**
     * GraphQLMutationContext constructor.
     * @param MutationScaffolder $mutation
     * @param SS_List|null $list
     * @param ViewableData|null $record
     * @param array $args
     * @param array $graphqlContext
     * @param ResolveInfo|null $info
     */
    public function __construct(
        MutationScaffolder $mutation,
        ?SS_List $list = null,
        ?ViewableData $record = null,
        array $args = [],
        array $graphqlContext = [],
        ?ResolveInfo $info = null
    ) {
        $this->mutation = $mutation;
        $this->list = $list;
        $this->record = $record;
        $this->args = $args;
        $this->graphqlContext = $graphqlContext;
        $this->info = $info;
    }

    /**
     * @return MutationScaffolder
     */
    public function getMutation(): MutationScaffolder
    {
        return $this->mutation;
    }

    /**
     * @return SS_List|null
     */
    public function getList(): ?SS_List
    {
        return $this->list;
    }

    /**
     * @return ViewableData|null
     */
    public function getRecord(): ?ViewableData
    {
        return $this->record;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return array
     */
    public function getGraphqlContext(): array
    {
        return $this->graphqlContext;
    }

    /**
     * @return ResolveInfo|null
     */
    public function getInfo(): ?ResolveInfo
    {
        return $this->info;
    }

    public function getAction(): string
    {
        $scaffolder = $this->getMutation();
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
