<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\Elemental\ModifyElementHandler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class ModifyElementHandlerTest extends SnapshotTestAbstract
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
        $handler = ModifyElementHandler::create();
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
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = ModifyElementHandler::create();
        $block = BaseElement::create();
        $block->write();

        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot')
            ->with($this->callback(static function ($arg) use ($block) {
                return $arg instanceof BaseElement && $arg->ID == $block->ID;
            }));

        $context = Event::create('action', [
            'params' => [
                'blockId' => $block->ID,
            ],
        ]);

        $handler->fire($context);
    }

    /**
     * @throws ValidationException
     * @dataProvider dataProvider
     */
    public function testHandlerSetsPublishState(string $actionName, bool $wasPublished, bool $wasUnpublished): void
    {
        $handler = ModifyElementHandler::create();
        $block = BaseElement::create();
        $block->write();
        $context = Event::create($actionName, [
            'params' => [
                'blockId' => $block->ID,
            ],
        ]);

        $handler->fire($context);

        /** @var Snapshot $snapshot */
        $snapshot = Snapshot::get()
            ->sort('ID', 'DESC')
            ->first();
        $this->assertNotNull($snapshot);

        $item = $snapshot->getOriginItem();
        $this->assertNotNull($item);
        $this->assertEquals($wasPublished, (bool) $item->WasPublished);
        $this->assertEquals($wasUnpublished, (bool) $item->WasUnpublished);
    }

    /**
     * @return array
     */
    public function dataProvider(): array
    {
        return [
            ['PublishBlock', true, false],
            ['UnpublishBlock', false, true],
        ];
    }
}
