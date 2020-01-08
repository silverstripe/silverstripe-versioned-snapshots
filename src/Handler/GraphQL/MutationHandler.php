<?php


namespace SilverStripe\Snapshots\Handler\GraphQL;


use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Create;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\OperationScaffolder;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Handler\HandlerInterface;
use SilverStripe\Snapshots\Listener\CurrentPage;

class MutationHandler extends HandlerAbstract implements HandlerInterface
{

    const TYPE_CREATE = 'create';
    const TYPE_DELETE = 'delete';
    const TYPE_UPDATE = 'update';
    const ACTION_PREFIX = 'graphql_crud_';

    /**
     * @var string
     */
    protected $mutationType;

    /**
     * MutationHandler constructor.
     * @param string $mutationType
     */
    public function __construct(string $mutationType)
    {
        $this->mutationType = $mutationType;
    }

    public function shouldFire(Context $context): bool
    {
        $type = $this->getActionType($context->mutation);

        return (
            parent::shouldFire($context) &&
            $type &&
            $type === $this->mutationType
        );
    }

    public function fire(Context $context): void
    {
        $action = static::ACTION_PREFIX . $type;
        $message = $this->getMessage();

        $controller = Controller::curr();

        if (!$controller) {
            return;
        }

        $request = $controller->getRequest();

        if (!$request) {
            return;
        }

        $url = $request->getHeader('referer');
        $url = parse_url($url, PHP_URL_PATH);
        $url = ltrim($url, '/');
        $page = $this->getCurrentPageFromRequestUrl($url);

        if ($page === null) {
            return;
        }

        $snapshot->createSnapshotFromAction($page, null, $message);
    }

    /**
     * @param OperationScaffolder $scaffolder
     * @return string|null
     */
    private function getActionType(OperationScaffolder $scaffolder): ?string
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
