<?php


namespace SilverStripe\Snapshots\Handler\GridField;


use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Handler\HandlerInterface;
use SilverStripe\Snapshots\Listener\CurrentPage;
use SilverStripe\Snapshots\Snapshot;

class ActionHandler extends HandlerAbstract implements HandlerInterface
{

    protected $action;

    protected $message;

    public function shouldFire(Context $context): bool
    {
        return (
            parent::shouldFire($context) &&
            $this->action === $context->action
        );
    }

    public function __construct(string $action, $message)
    {
        $this->action = $action;
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function fire(Context $context): void
    {
        $message = $this->getMessage();
        $form = $owner->getForm();

        if (!$form) {
            return;
        }

        $record = $form->getRecord();

        if (!$record) {
            return;
        }

        $page = $this->getCurrentPageFromController($form);

        if ($page === null) {
            return;
        }

        // attempt to create a custom snapshot first
        $customSnapshot = $snapshot->gridFieldUrlActionSnapshot($page, $action, $message, $owner);

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, $record, $message);

    }
}
