<?php

namespace SilverStripe\Snapshots\Tests\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Elemental\ArchiveElementHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

require_once(__DIR__ . '/../../SnapshotTestAbstract.php');
class ArchiveElementHandlerTest extends SnapshotTestAbstract
{
    protected function setUp()
    {
        parent::setUp();
        BlockPage::add_extension(ElementalPageExtension::class);
    }

    public function testHandlerDoesntFire()
    {
        $handler = new ArchiveElementHandler();
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
                'params' => []
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
        $id = BaseElement::create()->write();
        $context = Event::create(
            'action',
            [
                'params' => ['blockId' => $id]
            ]
        );
        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = new ArchiveElementHandler();
        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot');

        $elem = BaseElement::create();
        $elem->write();
        $this->createHistory($elem);
        $elem->doArchive();
        $context = Event::create(
            'action',
            [
                'params' => ['blockId' => $elem->ID]
            ]
        );
        $handler->fire($context);
    }
}
