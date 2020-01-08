<?php

namespace SilverStripe\Snapshots\Listener;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\FormAction\StateStore;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Listener\CurrentPage;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class UrlHandlerAction
 *
 * Snapshot action listener for grid field actions
 *
 * @property GridField|$this $owner
 * @package SilverStripe\Snapshots\Listener\GridField
 */
class GridFieldURLListener extends Extension
{

    use CurrentPage;

    /**
     * Extension point in @see GridField::handleRequest
     * GridField action via custom URL handler
     * covers action which are implemented via @see GridField_URLHandler
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $result
     * @throws ValidationException
     */
    public function afterCallActionURLHandler( // phpcs:ignore SlevomatCodingStandard.TypeHints
        HTTPRequest $request,
        $action,
        $result
    ): void {
        Dispatcher::singleton()->trigger('gridFieldAction', new Context([
            'action' => $action,
            'result' => $result,
            'request' => $request,
            'gridField' => $this->owner,
        ]));
    }
}
