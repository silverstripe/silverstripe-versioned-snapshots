<?php


namespace SilverStripe\Snapshots\Handler\CMSMain;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Handler\HandlerInterface;
use SilverStripe\Snapshots\Snapshot;

class ActionHandler extends HandlerAbstract implements HandlerInterface
{
    protected $message;

    protected $action;

    public function __construct(string $action, string $message)
    {
        $this->action = $action;
        $this->message = $message;
    }

    public function shouldFire(Context $context): bool
    {
        return (
            parent::shouldFire($context) &&
            $context->action === $this->action
        );
    }

    public function fire(Context $context): void
    {
        $message = $this->getMessage();

        if (!$context->result instanceof HTTPResponse) {
            return;
        }

        if ((int) $context->result->getStatusCode() !== 200) {
            return;
        }

        $className = $context->treeClass;
        $id = (int) $context->id;

        if (!$id) {
            return;
        }

        /** @var SiteTree $page */
        $page = DataObject::get_by_id($className, $id);

        if ($page === null) {
            return;
        }

        $snapshot->createSnapshotFromAction($page, null, $message);
    }
}
