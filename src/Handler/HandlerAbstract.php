<?php


namespace SilverStripe\Snapshots\Handler;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;
use SilverStripe\Snapshots\Snapshot;

abstract class HandlerAbstract implements EventHandlerInterface
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

    public function fire(EventContextInterface $context): void
    {
        $this->createSnapshot($context);
    }

    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     */
    abstract protected function createSnapshot(EventContextInterface $context): ?Snapshot;
}
