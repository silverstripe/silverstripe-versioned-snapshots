<?php

namespace SilverStripe\Snapshots\Tests\Elemental;

use DNADesign\Elemental\Controllers\ElementalAreaController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Snapshots\Elemental\ElementalAreaListener;

class ElementalAreaListenerTest extends SapphireTest
{
    public function testListenerDoesFire(): void
    {
        $controller = ElementalAreaController::create();
        $request = new HTTPRequest('POST', '/', [], [], json_encode(['id' => 12]));
        $action = 'apiSort';
        $response = HTTPResponse::create('', 204);

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher
            ->expects($this->once())
            ->method('trigger')
            ->with(
                ElementalAreaListener::EVENT_NAME,
                $this->callback(static function (EventContextInterface $context): bool {
                    return $context->getAction() === 'apiSort' && $context->get('data') === ['id' => 12];
                })
            );
        Injector::inst()->registerService($dispatcher, Dispatcher::class);

        $controller->extend('afterCallActionHandler', $request, $action, $response);
    }

    public function testListenerDoesFireWithInvalidBody(): void
    {
        $controller = ElementalAreaController::create();
        $request = new HTTPRequest('POST', '/', [], [], 'invalid');
        $action = 'apiCreate';
        $response = HTTPResponse::create('', 204);

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher
            ->expects($this->once())
            ->method('trigger')
            ->with(
                ElementalAreaListener::EVENT_NAME,
                $this->callback(static function (EventContextInterface $context): bool {
                    return $context->get('data') === [];
                })
            );
        Injector::inst()->registerService($dispatcher, Dispatcher::class);

        $controller->extend('afterCallActionHandler', $request, $action, $response);
    }

    public function testListenerDoesntFire(): void
    {
        $controller = ElementalAreaController::create();
        $request = new HTTPRequest('POST', '/', [], [], json_encode(['id' => 12]));
        $action = 'apiDelete';

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher
            ->expects($this->never())
            ->method('trigger');
        Injector::inst()->registerService($dispatcher, Dispatcher::class);

        // Error response
        $response = HTTPResponse::create('', 400);
        $controller->extend('afterCallActionHandler', $request, $action, $response);

        // Result is not a response
        $form = Form::create($controller, 'Form', FieldList::create(), FieldList::create());
        $controller->extend('afterCallActionHandler', $request, $action, $form);
    }
}
