<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Elemental\DuplicateElementHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class DuplicateElementHandlerTest extends SnapshotTestAbstract
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
        $handler = DuplicateElementHandler::create();
        $mockSnapshot = $this->mockSnapshot();

        $context = Event::create(null, []);
        $handler->fire($context);

        $context = Event::create('action', []);
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'data' => [],
            ]
        );
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'data' => ['id' => 5],
            ]
        );
        $handler->fire($context);

        $createSnapshotEventCount = $mockSnapshot->wasMethodCalled('createSnapshotEvent');
        $this->assertEquals(0, $createSnapshotEventCount, 'We expect to not trigger the event handler');
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = DuplicateElementHandler::create();
        $area = ElementalArea::create();
        $area->write();

        $block = BaseElement::create();
        $block->Title = 'Block';
        $block->ParentID = $area->ID;
        $block->Sort = 1;
        $block->write();

        $duplicate = BaseElement::create();
        $duplicate->Title = 'Block copy';
        $duplicate->ParentID = $area->ID;
        $duplicate->Sort = 2;
        $duplicate->write();

        $mockSnapshot = $this->mockSnapshot();

        $context = Event::create('action', [
            'data' => [
                'id' => $block->ID,
            ],
        ]);

        $handler->fire($context);

        $createSnapshotEventCountWithParams = $mockSnapshot->wasMethodCalled(
            'createSnapshotEvent',
            static function (array $params): bool {
                if (!array_key_exists('message', $params)) {
                    return false;
                }

                return $params['message'] === 'Duplicated block "Block"';
            }
        );
        $this->assertEquals(
            1,
            $createSnapshotEventCountWithParams,
            'We expect to trigger the event handler (params)'
        );

        $addOwnershipChainCountWithParams = $mockSnapshot->wasMethodCalled(
            'addOwnershipChain',
            static function (array $params) use ($duplicate): bool {
                if (!array_key_exists('model', $params)) {
                    return false;
                }

                $model = $params['model'];

                return $model instanceof BaseElement && $model->ID === $duplicate->ID;
            }
        );
        $this->assertEquals(
            1,
            $addOwnershipChainCountWithParams,
            'We expect the duplicated block in the snapshot'
        );
    }
}
