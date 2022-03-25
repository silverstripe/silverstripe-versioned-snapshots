<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\Elemental\CMSActionsHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class CMSActionsHandlerTest extends SnapshotTestAbstract
{
    /**
     * @var array
     */
    protected static $required_extensions = [
        BlockPage::class => [
            ElementalPageExtension::class,
        ],
    ];

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesntFire(): void
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
                'request' => new HTTPRequest('GET', '/'),
            ]
        );
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'request' => (new HTTPRequest('GET', '/'))->setRouteParams([
                    'ID' => 5,
                ]),
            ]
        );
        $handler->fire($context);
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = CMSActionsHandler::create();
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
