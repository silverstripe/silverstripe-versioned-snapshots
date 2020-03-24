<?php

namespace SilverStripe\Snapshots\Tests\Handler\GraphQL\Middleware;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Elemental\SortElementsHandler;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\Handler;
use SilverStripe\Snapshots\Handler\PageContextProvider;
use SilverStripe\Snapshots\Tests\Handler\GraphQL\FakePageContextProvider;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class HandlerTest extends SnapshotTestAbstract
{
    protected function setUp()
    {
        parent::setUp();
        Injector::inst()->registerService(
            new FakePageContextProvider(),
            PageContextProvider::class
        );
    }

    public function testHandlerDoesntFire()
    {
        $handler = Handler::create();
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshot');
        $context = Event::create(null, []);

        $handler->fire($context);

        $context = Event::create('action', []);
        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = Handler::create();
        $blockPage = SiteTree::create();
        $blockPage->write();
        Injector::inst()->get(PageContextProvider::class)
            ->setPage($blockPage);

        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot')
            ->with($this->callback(function ($arg) use ($blockPage) {
                return $arg instanceof SiteTree && $arg->ID == $blockPage->ID;
            }));

        $context = Event::create('action', []);
        $handler->fire($context);
    }
}
