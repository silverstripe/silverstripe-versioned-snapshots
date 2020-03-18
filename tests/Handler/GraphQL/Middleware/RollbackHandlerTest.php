<?php

namespace SilverStripe\Snapshots\Tests\Handler\GraphQL\Middleware;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\GraphQL\Middleware\RollbackHandler;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

require_once(__DIR__ . '/../../../SnapshotTestAbstract.php');

class RollbackHandlerTest extends SnapshotTestAbstract
{
    public function testHandlerDoesntFire()
    {
        $handler = RollbackHandler::create();
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshotEvent');

        $context = Event::create(null, []);
        $handler->fire($context);

        $context = Event::create('action', []);
        $handler->fire($context);

        $context = Event::create('action', ['params' => ['some' => 'data']]);
        $handler->fire($context);

        $context = Event::create('action', [
            'params' => [
                'id' => 5,
                'toVersion' => 8,
            ]
        ]);
        $handler->fire($context);

        $page = SiteTree::create();
        $page->write();
        $currentVersion = $page->Version;

        $context = Event::create('action', [
            'params' => [
                'id' => $page->ID,
                'toVersion' => $currentVersion * 100,
            ]
        ]);

        $handler->fire($context);
    }

    public function testHandlerDoesFire()
    {
        $handler = RollbackHandler::create();

        $page = SiteTree::create(['Title' => 'test']);
        $page->write();
        $prevVersion = $page->Version;
        $this->createHistory($page);
        $page->Title = 'test2';
        $page->write();

        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshotEvent')
            ->with($this->equalTo('Rolled back to version ' . $prevVersion));

        $context = Event::create('rollbackBlock', [
            'params' => [
                'id' => $page->ID,
                'toVersion' => $prevVersion,
            ]
        ]);

        $handler->fire($context);
    }
}
