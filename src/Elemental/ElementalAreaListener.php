<?php

namespace SilverStripe\Snapshots\Elemental;

use DNADesign\Elemental\Controllers\ElementalAreaController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;

/**
 * Event hook for @see ElementalAreaController
 *
 * Elemental 6 replaced the GraphQL mutations of the inline editor with REST endpoints
 * (apiCreate, apiDelete, apiDuplicate, apiPublish, apiSort, apiUnpublish).
 * This listener fires an event after each successful action, with the decoded JSON body as context.
 *
 * @extends Extension<ElementalAreaController>
 */
class ElementalAreaListener extends Extension
{
    public const string EVENT_NAME = 'elementalAreaAction';

    /**
     * Extension point in @see RequestHandler::handleAction()
     *
     * Failed actions call jsonError(), which throws, so this hook runs only for actions that did not fail.
     * The error response check is a safeguard for actions that return an error response.
     */
    protected function afterCallActionHandler(HTTPRequest $request, string $action, mixed $result): void
    {
        if (!$result instanceof HTTPResponse) {
            return;
        }

        if ($result->isError()) {
            return;
        }

        Dispatcher::singleton()->trigger(
            ElementalAreaListener::EVENT_NAME,
            Event::create(
                $action,
                [
                    'data' => $this->getPostedData($request),
                    'request' => $request,
                    'result' => $result,
                ]
            )
        );
    }

    private function getPostedData(HTTPRequest $request): array
    {
        $body = $request->getBody();

        if (!$body) {
            return [];
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return [];
        }

        return $data;
    }
}
