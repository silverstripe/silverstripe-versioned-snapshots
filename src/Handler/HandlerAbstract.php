<?php


namespace SilverStripe\Snapshots\Handler;


use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Listener\CurrentPage;
use SilverStripe\Snapshots\Snapshot;

abstract class HandlerAbstract
{
    use CurrentPage;

    abstract public function getMessage(): string;

    /**
     * @param Context $context
     * @return bool
     */
    public function shouldFire(Context $context): bool
    {
        return Snapshot::singleton()->isActionTriggerActive();
    }
}
