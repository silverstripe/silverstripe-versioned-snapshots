<?php


namespace SilverStripe\Snapshots\Dispatch;

interface EventHandlerLoader
{
    public function addToDispatcher(Dispatcher $dispatcher): void;
}
