<?php

namespace SilverStripe\Snapshots\Tests\Handler\GridField\Action;

use SilverStripe\Control\Controller;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Handler\GridField\Action\ReorderHandler;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class ReorderHandlerTest extends SnapshotTestAbstract
{
    public function testHandlerDoesFire()
    {
        $handler = ReorderHandler::create();
        $block = Block::create();
        $block->write();

        $mock = $this->mockSnapshot();
        $mock->method('createSnapshot')->willReturnSelf();
        $mock
            ->expects($this->once())
            ->method('applyOrigin')
            ->with($this->callback(function ($arg) use ($block) {
                return $arg instanceof SnapshotEvent &&
                    $arg->Title == 'Reordered ' . $block->i18n_plural_name();
            }));

        $form = Form::create(Controller::create(), 'TestForm', FieldList::create(), FieldList::create())
            ->loadDataFrom($block);
        $grid = GridField::create('Test', 'Test', Block::get());
        $grid->setForm($form);

        $context = Event::create('action', ['gridField' => $grid]);
        $handler->fire($context);
    }
}
