<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Elemental\SortElementsHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

require_once(__DIR__ . '/../../SnapshotTestAbstract.php');
class SortElementsHandlerTest extends SnapshotTestAbstract
{

    protected function setUp()
    {
        parent::setUp();
        BlockPage::add_extension(ElementalPageExtension::class);
    }

    public function testHandlerDoesntFire()
    {
        $handler = new SortElementsHandler();
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
                'params' => ['blockId' => 5]
            ]
        );
        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = new SortElementsHandler();
        $area = ElementalArea::create();
        $area->write();
        $block = BaseElement::create(['ParentID' => $area->ID]);
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
            ->with($this->callback(function ($arg) use ($area) {
                return $arg instanceof ElementalArea && $arg->ID == $area->ID;
            }));

        $context = Event::create('action', [
            'params' => [
                'blockId' => $block->ID
            ],
        ]);

        $handler->fire($context);
    }
}
