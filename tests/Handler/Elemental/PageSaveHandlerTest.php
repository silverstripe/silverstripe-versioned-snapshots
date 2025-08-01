<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Handler\Elemental\PageSaveHandler;
use SilverStripe\Snapshots\RelationDiffer\RelationDiffer;
use SilverStripe\Snapshots\Snapshot;
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
            ->onlyMethods(['getRelationDiffs'])
            ->getMock();
        $ext->expects($this->any())
            ->method('getRelationDiffs')
            ->willReturn([]);
        Injector::inst()->registerService($ext, RecursivePublishable::class);

        $area = ElementalArea::create();
        $area->OwnerClassName = BlockPage::class;
        $area->write();

        /** @var BlockPage|ElementalPageExtension $blockPage */
        $blockPage = BlockPage::create();
        $blockPage->ElementalAreaID = $area->ID;
        $blockPage->write();

        $this->createHistory($blockPage);
        $this->mockSnapshotLegacy()
            ->expects($this->never())
            ->method('createSnapshot');
        $this->mockSnapshotLegacy()
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
     */
    #[DataProvider('multipleBlockOptionsProvider')]
    public function testHandlerDoesFireMany(bool $many): void
    {
        $handler = PageSaveHandler::create();
        $block1 = BaseElement::create();
        $block1->write();
        $block2 = BaseElement::create();
        $block2->write();

        // Differ needs to return some element IDs. Either one or two, depending on the test.
        $differ = $this->getMockBuilder(RelationDiffer::class)
            ->onlyMethods(['getChanged'])
            ->setConstructorArgs([BaseElement::class, 'has_many'])
            ->getMock();
        $differ->expects($this->once())
            ->method('getChanged')
            ->willReturn(
                $many ? [
                    $block1->ID,
                    $block2->ID,
                ] : [
                    $block1->ID,
                ]
            );

        // Ensure the getRelationDiffs() function returns the mocked differ
        $ext = $this->getMockBuilder(SnapshotPublishable::class)
            ->onlyMethods([
                'getRelationDiffs',
            ])
            ->getMock();
        $ext->expects($this->any())
            ->method('getRelationDiffs')
            ->willReturn([
                $differ,
            ]);

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

        $mockSnapshot = $this->mockSnapshot();

        $form = Form::create(Controller::create(), 'TestForm', FieldList::create(), FieldList::create());
        $form->loadDataFrom($blockPage);
        $context = Event::create('action', [
            'form' => $form,
        ]);

        $handler->fire($context);

        // If many elements are returned, we should get an event and add block1, block2.
        // If only one, expect a standard snapshot with block1
        $createSnapshotCount = $mockSnapshot->wasMethodCalled('createSnapshot');
        $createSnapshotEventCount = $mockSnapshot->wasMethodCalled('createSnapshotEvent');

        // If many elements are returned, the ownership chain should be created
        // with block1 and block2
        $addOwnershipChainCount = $mockSnapshot->wasMethodCalled('addOwnershipChain');

        if ($many) {
            $this->assertEquals(
                0,
                $createSnapshotCount,
                'We expect to not trigger the event handler (createSnapshot method)'
            );
            $this->assertEquals(
                1,
                $createSnapshotEventCount,
                'We expect to trigger the event handler (createSnapshotEvent method)'
            );

            // If many elements are returned, the ownership chain should be created
            // with block1 and block2
            $this->assertEquals(
                2,
                $addOwnershipChainCount,
                'We expect to trigger the event handler (addOwnershipChainCount method)'
            );

            $addOwnershipChainWithParams = $mockSnapshot->wasMethodCalled(
                'addOwnershipChain',
                static function (array $params) use ($block1, $block2): bool {
                    if (!array_key_exists('model', $params)) {
                        return false;
                    }

                    /** @var DataObject $model */
                    $model = $params['model'];

                    if ($model->ClassName === $block1->ClassName && $model->ID === $block1->ID) {
                        return true;
                    }

                    if ($model->ClassName === $block2->ClassName && $model->ID === $block2->ID) {
                        return true;
                    }

                    return false;
                }
            );
            $this->assertEquals(
                2,
                $addOwnershipChainWithParams,
                'We expect to trigger the event handler (addOwnershipChain params)'
            );
        } else {
            $this->assertEquals(
                1,
                $createSnapshotCount,
                'We expect to trigger the event handler (createSnapshot method)'
            );
            $this->assertEquals(
                0,
                $createSnapshotEventCount,
                'We expect to not trigger the event handler (createSnapshotEvent method)'
            );
            $this->assertEquals(
                0,
                $addOwnershipChainCount,
                'We expect to not trigger the event handler (addOwnershipChainCount method)'
            );

            $createSnapshotEventCountWithParams = $mockSnapshot->wasMethodCalled(
                'createSnapshot',
                static function (array $params) use ($block1): bool {
                    if (!array_key_exists('origin', $params)) {
                        return false;
                    }

                    /** @var DataObject $origin */
                    $origin = $params['origin'];

                    return $origin->ClassName === $block1->ClassName && $origin->ID === $block1->ID;
                }
            );
            $this->assertEquals(
                1,
                $createSnapshotEventCountWithParams,
                'We expect to trigger the event handler (createSnapshot params)'
            );
        }
    }

    public static function multipleBlockOptionsProvider(): array
    {
        return [
            'multiple blocks' => [
                true,
            ],
            'single block' => [
                false,
            ],
        ];
    }
}
