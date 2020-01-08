<?php


namespace SilverStripe\Snapshots\Handler;


use SilverStripe\Snapshots\Dispatch\Context;

interface HandlerInterface
{
    public function fire(Context $context): void;
}
