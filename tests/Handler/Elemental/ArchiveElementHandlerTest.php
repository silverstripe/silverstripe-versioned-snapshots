<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\Elemental\ArchiveElementHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;
use SilverStripe\Versioned\Versioned;

class ArchiveElementHandlerTest extends SnapshotTestAbstract
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
        $handler = ArchiveElementHandler::create();
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
                'params' => [],
            ]
        );
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'params' => ['blockId' => 5],
            ]
        );
        $handler->fire($context);
        $id = BaseElement::create()->write();
        $context = Event::create(
            'action',
            [
                'params' => ['blockId' => $id],
            ]
        );
        $handler->fire($context);
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = ArchiveElementHandler::create();
        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot');

        /** @var BaseElement|Versioned $elem */
        $elem = BaseElement::create();
        $elem->write();
        $this->createHistory($elem);
        $elem->doArchive();
        $context = Event::create(
            'action',
            [
                'params' => ['blockId' => $elem->ID],
            ]
        );
        $handler->fire($context);
    }
}
