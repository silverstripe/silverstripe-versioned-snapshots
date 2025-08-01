<?php

namespace SilverStripe\Snapshots\Handler;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Versioned\Versioned;

/**
 * Parent class intended for event hooks
 */
abstract class HandlerAbstract implements EventHandlerInterface
{

    use Configurable;
    use Injectable;

    /**
     * @config
     */
    private static array $messages = [];

    private ?PageContextProvider $pageContextProvider = null;

    private static array $dependencies = [
        'PageContextProvider' => '%$' . PageContextProvider::class,
    ];

    protected function getMessage(string $action): string
    {
        $messages = $this->config()->get('messages');

        if (isset($messages[$action])) {
            return $messages[$action];
        }

        $key = sprintf('%s.HANDLER_%s', static::class, $action);

        /** @phpstan-ignore translation.key (we need the key to be dynamic here) */
        return _t($key, $action);
    }

    protected function getDeletedVersion(string $recordClass, int $id): ?DataObject
    {
        return Versioned::get_including_deleted($recordClass)
            ->byID($id);
    }

    /**
     * @param EventContextInterface $context
     * @throws ValidationException
     */
    public function fire(EventContextInterface $context): void
    {
        $snapshot = $this->createSnapshot($context);

        if (!$snapshot) {
            return;
        }

        $snapshot->write();
    }

    public function setPageContextProvider(PageContextProvider $provider): HandlerAbstract
    {
        $this->pageContextProvider = $provider;

        return $this;
    }

    public function getPageContextProvider(): PageContextProvider
    {
        return $this->pageContextProvider;
    }

    abstract protected function createSnapshot(EventContextInterface $context): ?Snapshot;
}
