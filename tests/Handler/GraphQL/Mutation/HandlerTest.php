<?php

namespace SilverStripe\Snapshots\Tests\Handler\GraphQL\Mutation;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\GraphQL\Mutation\Handler;
use SilverStripe\Snapshots\Handler\PageContextProvider;
use SilverStripe\Snapshots\Tests\Handler\GraphQL\FakePageContextProvider;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class HandlerTest extends SnapshotTestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();

        $fakeContextProvider = FakePageContextProvider::create();
        Injector::inst()->registerService($fakeContextProvider, PageContextProvider::class);
    }

    /**
     * @return void
     * @throws ValidationException
     */
    public function testHandlerDoesntFire(): void
    {
        $handler = Handler::create();
        $this->mockSnapshotLegacy()
            ->expects($this->never())
            ->method('createSnapshot');
        $context = Event::create(null, []);

        $handler->fire($context);

        $context = Event::create('action', []);
        $handler->fire($context);
    }

    /**
     * @return void
     * @throws ValidationException
     * @throws NotFoundExceptionInterface
     */
    public function testHandlerDoesFire(): void
    {
        $handler = Handler::create();
        $blockPage = SiteTree::create();
        $blockPage->write();

        PageContextProvider::singleton()->setPage($blockPage);

        $this->mockSnapshotLegacy()
            ->expects($this->once())
            ->method('createSnapshot')
            ->with($this->callback(static function ($arg) use ($blockPage) {
                return $arg instanceof SiteTree && $arg->ID == $blockPage->ID;
            }));

        $context = Event::create('action', []);
        $handler->fire($context);
    }
}
