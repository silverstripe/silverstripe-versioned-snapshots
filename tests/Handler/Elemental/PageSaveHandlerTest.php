<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\Elemental\PageSaveHandler;
use SilverStripe\Snapshots\RelationDiffer;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;
use SilverStripe\Versioned\RecursivePublishable;

class PageSaveHandlerTest extends SnapshotTestAbstract
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
        $handler = PageSaveHandler::create();
        $ext = $this->getMockBuilder(SnapshotPublishable::class)
            ->setMethods(['getRelationDiffs'])
            ->getMock();
        $ext->expects($this->any())
            ->method('getRelationDiffs')
            ->will($this->returnValue([]));
        Injector::inst()->registerService($ext, RecursivePublishable::class);

        $area = ElementalArea::create();
        $area->OwnerClassName = BlockPage::class;
        $area->write();

        /** @var BlockPage|ElementalPageExtension $blockPage */
        $blockPage = BlockPage::create();
        $blockPage->ElementalAreaID = $area->ID;
        $blockPage->write();

        $this->createHistory($blockPage);
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshot');
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshotEvent');

        $form = Form::create(Controller::create(), 'TestForm', FieldList::create(), FieldList::create());
        $form->loadDataFrom($blockPage);
        $context = Event::create('action', [
            'form' => $form,
        ]);

        $handler->fire($context);
    }

    /**
     * @param bool $many
     * @throws ValidationException
     * @dataProvider dataProvider
     */
    public function testHandlerDoesFireMany(bool $many): void
    {
        $handler = PageSaveHandler::create();
        $block1 = BaseElement::create();
        $block1->write();
        $block2 = BaseElement::create();
        $block2->write();

        // Differ needs to return some element IDs. Either one or two, depending on the test.
        $differ = $this->getMockBuilder(RelationDiffer::class)
            ->setMethods(['getChanged'])
            ->setConstructorArgs([BaseElement::class, 'has_many'])
            ->getMock();
        $differ->expects($this->once())
            ->method('getChanged')
            ->will($this->returnValue(
                $many ? [$block1->ID, $block2->ID] : [$block1->ID]
            ));

        // Ensure the getRelationDiffs() function returns the mocked differ
        $ext = $this->getMockBuilder(SnapshotPublishable::class)
            ->setMethods(['getRelationDiffs'])
            ->getMock();
        $ext->expects($this->any())
            ->method('getRelationDiffs')
            ->will($this->returnValue([$differ]));

        Injector::inst()->registerService($ext, RecursivePublishable::class);

        // Now that the mock is registered, we can create the object
        $area = ElementalArea::create();
        $area->OwnerClassName = BlockPage::class;
        $area->write();

        /** @var BlockPage|ElementalPageExtension $blockPage */
        $blockPage = BlockPage::create();
        $blockPage->ElementalAreaID = $area->ID;
        $blockPage->write();
        $this->createHistory($blockPage);

        $mock = $this->mockSnapshot();
        // If many elements are returned, we should get an event and add block1, block2.
        // If only one, expect a standard snapshot with block1
        $mock->expects($many ? $this->never() : $this->once())
            ->method('createSnapshot')
            ->with($this->callback(static function ($sub) use ($block1) {
                return $sub->ClassName === $block1->ClassName && $sub->ID = $block1->ID;
            }));

        $mock->expects($many ? $this->once() : $this->never())
            ->method('createSnapshotEvent')
            ->will($this->returnSelf());

        // If many elements are returned, the ownership chain should be created
        // with block1 and block2
        $mock->expects($many ? $this->exactly(2) : $this->never())
            ->method('addOwnershipChain')
            ->withConsecutive(
                [
                    $this->callback(static function ($sub) use ($block1) {
                        return $sub->ClassName === $block1->ClassName && $sub->ID = $block1->ID;
                    }),
                ],
                [
                    $this->callback(static function ($sub) use ($block2) {
                        return $sub->ClassName === $block2->ClassName && $sub->ID = $block2->ID;
                    }),
                ]
            );

        $form = Form::create(Controller::create(), 'TestForm', FieldList::create(), FieldList::create());
        $form->loadDataFrom($blockPage);
        $context = Event::create('action', [
            'form' => $form,
        ]);

        $handler->fire($context);
    }

    public function dataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }
}
