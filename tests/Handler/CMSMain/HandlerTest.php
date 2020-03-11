<?php

namespace SilverStripe\Snapshots\Tests\Handler\CMSMain;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\CMSMain\Handler;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

require_once(__DIR__ . '/../../SnapshotTestAbstract.php');

class HandlerTest extends SnapshotTestAbstract
{
    public function testHandlerDoesntFire()
    {
        $handler = new Handler();
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshotEvent');

        $context = Event::create(null, []);
        $handler->fire($context);

        $context = Event::create('action', []);
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'result' => HTTPResponse::create('response', 400)
            ]
        );
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'result' => HTTPResponse::create('response', 200)
            ]
        );
        $handler->fire($context);

        $context = Event::create(
            'action',
            [
                'result' => HTTPResponse::create('response', 200),
                'id' => 5,
                'treeClass' => BlockPage::class,
            ]
        );
        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = new Handler();
        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshotEvent');

        $page = BlockPage::create();
        $id = $page->write();

        $handler->fire(Event::create(
            'action',
            [
                'result' => HTTPResponse::create('response', 200),
                'id' => $id,
                'treeClass' => BlockPage::class,
            ]
        ));
    }


}
