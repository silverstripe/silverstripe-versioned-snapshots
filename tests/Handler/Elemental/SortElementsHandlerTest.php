<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Symfony\Event;
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
        $mockSnapshot = $this->mockSnapshot();
        $handler = SortElementsHandler::create();

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

        $createSnapshotCount = $mockSnapshot->wasMethodCalled('createSnapshot');
        $this->assertEquals(
            0,
            $createSnapshotCount,
            'We expect to not trigger the event handler for snapshot'
        );
        $createSnapshotEventCount = $mockSnapshot->wasMethodCalled('createSnapshotEvent');
        $this->assertEquals(
            0,
            $createSnapshotEventCount,
            'We expect to not trigger the event handler for snapshot event'
        );
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

        $mockSnapshot = $this->mockSnapshot();

        $context = Event::create('action', [
            'params' => [
                'blockId' => $block->ID,
            ],
        ]);

        $handler->fire($context);

        $createSnapshotEventCount = $mockSnapshot->wasMethodCalled('createSnapshotEvent');
        $this->assertEquals(
            1,
            $createSnapshotEventCount,
            'We expect to trigger the event handler (method)'
        );

        $createSnapshotEventCountWithParams = $mockSnapshot->wasMethodCalled(
            'createSnapshotEvent',
            static function (array $params): bool {
                if (!array_key_exists('message', $params)) {
                    return false;
                }

                return $params['message'] === 'Reordered blocks';
            }
        );
        $this->assertEquals(
            1,
            $createSnapshotEventCountWithParams,
            'We expect to trigger the event handler (params)'
        );

        $addOwnershipChainCountWithParams = $mockSnapshot->wasMethodCalled(
            'addOwnershipChain',
            static function (array $params) use ($area): bool {
                if (!array_key_exists('model', $params)) {
                    return false;
                }

                $model = $params['model'];

                return $model instanceof ElementalArea && $model->ID === $area->ID;
            }
        );
        $this->assertEquals(
            1,
            $addOwnershipChainCountWithParams,
            'We expect to trigger the event handler (params)'
        );
    }
}
