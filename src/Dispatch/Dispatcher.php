<?php

namespace SilverStripe\Snapshots\Dispatch;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Snapshots\Handler\HandlerInterface;
use InvalidArgumentException;
use Exception;
use SilverStripe\Snapshots\Listener\EventContext;

class Dispatcher
{
    use Injectable;

    /**
     * @var array HandlerInterface[]
     */
    private $handlers = [];

    /**
     * Dispatcher constructor.
     * @param EventHandlerLoader[] $loaders
     */
    public function __construct($loaders = [])
    {
        foreach ($loaders as $loader) {
            if (!$loader instanceof EventHandlerLoader) {
                throw new InvalidArgumentException(sprintf(
                    '%s not passed an instance of %s',
                    __CLASS__,
                    EventHandlerLoader::class
                ));
            }

            $loader->addToDispatcher($this);
        }
    }

    /**
     * @param array $handlers
     * @throws Exception
     */
    public function setHandlers(array $handlers)
    {
        foreach ($handlers as $spec) {
            if (!isset($spec['handler']) || !isset($spec['on'])) {
                throw new InvalidArgumentException('Event handlers must have a "on" and "handler" nodes');
            }
            $on = is_array($spec['on']) ? $spec['on'] : [$spec['on']];
            $handler = $spec['handler'];

            if (!$handler instanceof HandlerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Handler for %s is not an instance of %s',
                    implode(', ', $on),
                    HandlerInterface::class
                ));
            }

            foreach ($on as $eventName) {
                $this->addListener($eventName, $handler);
            }
        }
    }

    /**
     * @param string $event
     * @param HandlerInterface $handler
     * @return $this
     * @throws Exception
     */
    public function addListener(string $event, HandlerInterface $handler): self
    {
        if (!isset($this->handlers[$event])) {
            $this->handlers[$event] = [];
        }

        foreach ($this->handlers[$event] as $existing) {
            if ($existing === $handler) {
                throw new Exception(sprintf(
                    'Handler for %s has already been added',
                    $event
                ));
            }
        }
        $this->handlers[$event][] = $handler;

        return $this;
    }

    /**
     * @param string $event
     * @param HandlerInterface $handler
     * @return $this
     */
    public function removeListener(string $event, HandlerInterface $handler): self
    {
        $handlers = $this->handlers[$event] ?? [];
        $this->handlers = array_filter($handlers, function ($existing) use ($handler) {
            return $existing !== $handler;
        });

        return $this;
    }

    /**
     * @param string $event
     * @param string $className
     * @return $this
     */
    public function removeListenerByClassName(string $event, string $className): self
    {
        $handlers = $this->handlers[$event] ?? [];
        $this->handlers = array_filter($handlers, function ($existing) use ($className) {
            return get_class($existing) !== $className;
        });

        return $this;
    }

    /**
     * @param string $event
     * @param EventContext $context
     */
    public function trigger(string $event, EventContext $context): void
    {
        $action = $context->getAction();
        // First fire listeners to <eventName.actionName>, then just fire generic <eventName> listeners
        $eventsToFire = [ $event . '.' . $action, $event];
        foreach ($eventsToFire as $event) {
            $handlers = $this->handlers[$event] ?? [];
            /* @var HandlerInterface $handler */
            foreach ($handlers as $handler) {
                $handler->fire($context);
            }
        }
    }
}
