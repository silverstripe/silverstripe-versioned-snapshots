<?php


namespace SilverStripe\Snapshots\Handler;

use SilverStripe\Snapshots\Listener\EventContext;

interface HandlerInterface
{
    public function fire(EventContext $context): void;
}
