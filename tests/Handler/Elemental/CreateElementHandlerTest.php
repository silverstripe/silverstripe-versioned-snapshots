<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\Elemental\CreateElementHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class CreateElementHandlerTest extends SnapshotTestAbstract
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
        $handler = CreateElementHandler::create();
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
                'params' => ['elementalAreaID' => 5],
            ]
        );
        $handler->fire($context);
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = CreateElementHandler::create();
        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot');

        $area = ElementalArea::create();
        $area->write();

        $context = Event::create('action', [
            'params' => [
                'elementalAreaID' => $area->ID,
            ],
        ]);

        $handler->fire($context);
    }
}
