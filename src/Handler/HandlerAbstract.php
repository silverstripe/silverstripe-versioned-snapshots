<?php


namespace SilverStripe\Snapshots\Handler;


use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Listener\CurrentPage;
use SilverStripe\Snapshots\Listener\EventContext;
use SilverStripe\Snapshots\Snapshot;

abstract class HandlerAbstract implements HandlerInterface
{
    use CurrentPage;
    use Configurable;

    /**
     * @var array
     * @config
     */
    private static $messages = [];

    /**
     * @param string $action
     * @return string
     */
    protected function getMessage(string $action): string
    {
        $messages = $this->config()->get('messages');
        if (isset($messages[$action])) {
            return $messages[$action];
        }

        $key = static::class . '.HANDLER_' . $action;
        return _t($key, $action);
    }

    public function fire(EventContext $context): void
    {
        $this->createSnapshot($context);
    }

    /**
     * @param EventContext $context
     * @return Snapshot|null
     */
    abstract protected function createSnapshot(EventContext $context): ?Snapshot;
}
