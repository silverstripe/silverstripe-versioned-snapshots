<?php

namespace SilverStripe\Snapshots\Dispatch;

use SilverStripe\Snapshots\Handler\HandlerInterface;

class Dispatcher
{
    /**
     * @var array HandlerInterface[]
     */
    private $handlers = [];

    /**
     * @param array $handlers
     */
    public function setHandlers(array $handlers)
    {
        foreach ($handlers as $spec) {
            list ($eventName, $handler) = $spec;
            if (!$handler instanceof HandlerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Handler for %s is not an instance of %s',
                    $event,
                    HandlerInterface::class
                ));
            }

            $this->addListener($event, $handler);
        }
    }

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

    public function removeListener(string $event, HandlerInterface $handler): self
    {
        $handlers = $this->handlers[$event] ?? [];
        /* @var HandlerInterface $handler */
        $this->handlers = array_filter(function ($existing) use ($handler) {
            return $handler !== $existing;
        }, $this->handlers);

        return $this;
    }

    public function trigger(string $event, Context $context): void
    {
        $handlers = $this->handlers[$event] ?? [];
        /* @var HandlerInterface $handler */
        foreach ($handlers as $handler) {
            if ($handler->shouldFire($context)) {
                $handler->fire($context);
            }
        }
    }
}
