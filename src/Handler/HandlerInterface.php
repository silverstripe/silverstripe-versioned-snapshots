<?php


namespace SilverStripe\Snapshots\Handler;


use SilverStripe\Snapshots\Listener\ListenerContext;

interface HandlerInterface
{
    public function fire(ListenerContext $context): void;

}
