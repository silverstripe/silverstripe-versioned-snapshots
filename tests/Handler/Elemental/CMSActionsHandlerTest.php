<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Elemental\CMSActionsHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class CMSActionsHandlerTest extends SnapshotTestAbstract
{

    protected function setUp()
    {
        parent::setUp();
        BlockPage::add_extension(ElementalPageExtension::class);
    }

    public function testHandlerDoesntFire()
    {
        $handler = new CMSActionsHandler();
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshot');

        $context = Event::create(null, []);
        $handler->fire($context);

        $context = Event::create('action', []);
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'request' => new HTTPRequest('GET', '/')
            ]
        );
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'request' => (new HTTPRequest('GET', '/'))->setRouteParams([
                    'ID' => 5
                ])
            ]
        );
        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = new CMSActionsHandler();
        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot');

        $block = BaseElement::create();
        $block->write();

        $context = Event::create(
            'action',
            [
                'request' => (new HTTPRequest('GET', '/'))->setRouteParams([
                    'ID' => $block->ID,
                ]),
            ]
        );
        $handler->fire($context);
    }
}
