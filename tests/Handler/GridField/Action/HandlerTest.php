<?php

namespace SilverStripe\Snapshots\Tests\Handler\GridField\Action;

use SilverStripe\Control\Controller;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\GridField\Action\Handler;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTestAbstract;

class HandlerTest extends SnapshotTestAbstract
{
    /**
     * @throws ValidationException
     */
    public function testHandlerDoesntFire(): void
    {
        $handler = Handler::create();
        $this->mockSnapshot()
            ->expects($this->never())
            ->method('createSnapshot');

        $context = Event::create(null, []);
        $handler->fire($context);

        $context = Event::create('action', ['gridField' => new GridField('test')]);
        $handler->fire($context);

        $form = Form::create(Controller::create(), 'TestForm', FieldList::create(), FieldList::create());
        $grid = GridField::create('Test');
        $grid->setForm($form);

        $context = Event::create('action', ['gridField' => $grid]);
        $handler->fire($context);
    }

    /**
     * @throws ValidationException
     */
    public function testHandlerDoesFire(): void
    {
        $handler = Handler::create();
        $block = Block::create();
        $block->write();

        $this->mockSnapshot()
            ->expects($this->once())
            ->method('createSnapshot')
            ->with($this->callback(static function ($arg) use ($block) {
                return $arg instanceof Block && $arg->ID == $block->ID;
            }));

        $form = Form::create(Controller::create(), 'TestForm', FieldList::create(), FieldList::create())
            ->loadDataFrom($block);
        $grid = GridField::create('Test');
        $grid->setForm($form);

        $context = Event::create('action', ['gridField' => $grid]);
        $handler->fire($context);
    }
}
