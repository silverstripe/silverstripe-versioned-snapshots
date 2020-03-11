<?php


namespace SilverStripe\Snapshots\Handler;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Event\EventHandlerInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Versioned\Versioned;

abstract class HandlerAbstract implements EventHandlerInterface
{
    use Configurable;
    use Injectable;

    /**
     * @var array
     * @config
     */
    private static $messages = [];

    /**
     * @var PageContextProvider
     */
    private $pageContextProvider;

    /**
     * @var array
     */
    private static $dependencies = [
        'PageContextProvider' => '%$' . PageContextProvider::class,
    ];

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

    /**
     * @param string $recordClass
     * @param int $id
     * @return DataObject|null
     */
    protected function getDeletedVersion(string $recordClass, int $id): ?DataObject
    {
        return Versioned::get_including_deleted($recordClass)
            ->byID($id);
    }

    public function fire(EventContextInterface $context): void
    {
        $snapshot = $this->createSnapshot($context);
        if ($snapshot) {
            $snapshot->write();
        }
    }

    /**
     * @param PageContextProvider $provider
     * @return $this
     */
    public function setPageContextProvider(PageContextProvider $provider): self
    {
        $this->pageContextProvider = $provider;

        return $this;
    }

    /**
     * @return PageContextProvider
     */
    public function getPageContextProvider(): PageContextProvider
    {
        return $this->pageContextProvider;
    }


    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     */
    abstract protected function createSnapshot(EventContextInterface $context): ?Snapshot;
}
