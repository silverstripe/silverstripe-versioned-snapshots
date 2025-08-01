<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Symfony\Event;
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
        $mockSnapshot = $this->mockSnapshot();
        $handler = ArchiveElementHandler::create();

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
        $blockID = BaseElement::create()->write();
        $context = Event::create(
            'action',
            [
                'params' => [
                    'blockId' => $blockID,
                ],
            ]
        );
        $handler->fire($context);

        $createSnapshotCount = $mockSnapshot->wasMethodCalled('createSnapshot');
        $this->assertEquals(0, $createSnapshotCount, 'We expect to not trigger the event handler');
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = ArchiveElementHandler::create();

        /** @var BaseElement|Versioned $block */
        $block = BaseElement::create();
        $block->write();
        $this->createHistory($block);

        $mockSnapshot = $this->mockSnapshot();

        $block->doArchive();
        $context = Event::create(
            'action',
            [
                'params' => [
                    'blockId' => $block->ID,
                ],
            ]
        );
        $handler->fire($context);

        $createSnapshotCount = $mockSnapshot->wasMethodCalled('createSnapshot');
        $this->assertEquals(1, $createSnapshotCount, 'We expect to trigger the event handler');
    }
}
