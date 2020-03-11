<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Elemental\ModifyElementHandler;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

require_once(__DIR__ . '/../../SnapshotTestAbstract.php');
class ModifyElementHandlerTest extends SnapshotTestAbstract
{

    protected function setUp()
    {
        parent::setUp();
        BlockPage::add_extension(ElementalPageExtension::class);
    }

    public function testHandlerDoesntFire()
    {
        $handler = new ModifyElementHandler();
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
                'params' => ['blockId' => 5]
            ]
        );
        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = new ModifyElementHandler();
        $block = BaseElement::create();
        $block->write();

        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot')
            ->with($this->callback(function ($arg) use ($block) {
                return $arg instanceof BaseElement && $arg->ID == $block->ID;
            }));

        $context = Event::create('action', [
            'params' => [
                'blockId' => $block->ID
            ],
        ]);

        $handler->fire($context);
    }

    /**
     * @throws \SilverStripe\ORM\ValidationException
     * @dataProvider dataProvider
     */
    public function testHandlerSetsPublishState($actionName, $wasPublished, $wasUnpublished)
    {
        $handler = new ModifyElementHandler();
        $block = BaseElement::create();
        $block->write();
        $context = Event::create($actionName, [
            'params' => [
                'blockId' => $block->ID
            ],
        ]);

        $handler->fire($context);

        /* @var Snapshot $snapshot */
        $snapshot = Snapshot::get()->sort('ID', 'DESC')->first();
        $this->assertNotNull($snapshot);

        $item = $snapshot->getOriginItem();
        $this->assertNotNull($item);
        $this->assertEquals($wasPublished, (bool) $item->WasPublished);
        $this->assertEquals($wasUnpublished, (bool) $item->WasUnpublished);
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return [
            ['PublishBlock', true, false],
            ['UnpublishBlock', false, true],
        ];
    }


}
