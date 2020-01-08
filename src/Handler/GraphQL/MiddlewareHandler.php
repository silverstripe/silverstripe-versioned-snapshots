<?php


namespace SilverStripe\Snapshots\Handler\GraphQL;


use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Handler\HandlerInterface;
use SilverStripe\Snapshots\Snapshot;

class MiddlewareHandler extends HandlerAbstract implements HandlerInterface
{
    /**
     * @var string
     */
    protected $actionType;

    /**
     * MiddlewareHandler constructor.
     * @param string $actionType
     */
    public function __construct(string $actionType)
    {
        $this->actionType = $actionType;
    }

    /**
     * @param Context $context
     * @return bool
     */
    public function shouldFire(Context $context): bool
    {
        $action = $this->getActionType($context->query);
        return (
            parent::shouldFire($context) &&
            $action &&
            $action === $this->actionType
        );
    }

    /**
     * @param Context $context
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function fire(Context $context): void
    {
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
     * Extract action type from query
     *
     * @param string $query
     * @return string|null
     */
    private function getActionType(string $query): ?string
    {
        $action = explode('(', $query);

        if (count($action) === 0) {
            return null;
        }

        $action = array_shift($action);

        if (!$action) {
            return null;
        }

        $action = str_replace(' ', '_', $action);

        return $action;
    }

}
