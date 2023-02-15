<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\Elemental\SortElementsHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class SortElementsHandlerTest extends SnapshotTestAbstract
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
        $handler = SortElementsHandler::create();
        $mock = $this->mockSnapshot();
        $mock
            ->expects($this->never())
            ->method('createSnapshot');
        $mock
            ->expects($this->never())
            ->method('createSnapshotEvent');

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
        $handler = SortElementsHandler::create();
        $area = ElementalArea::create();
        $area->write();

        $block = BaseElement::create();
        $block->ParentID = $area->ID;
        $block->write();

        $mock = $this->mockSnapshot();
        $mock
            ->expects($this->once())
            ->method('createSnapshotEvent')
            ->willReturnSelf()
            ->with($this->equalTo('Reordered blocks'));
        $mock
            ->expects($this->once())
            ->method('addOwnershipChain')
            ->with($this->callback(static function ($arg) use ($area) {
                return $arg instanceof ElementalArea && $arg->ID == $area->ID;
            }));

        $context = Event::create('action', [
            'params' => [
                'blockId' => $block->ID,
            ],
        ]);

        $handler->fire($context);
    }
}
