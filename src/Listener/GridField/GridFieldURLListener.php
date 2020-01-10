<?php

namespace SilverStripe\Snapshots\Listener\GridField;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Dispatch\Dispatcher;

/**
 * Class UrlHandlerAction
 *
 * Snapshot action listener for grid field actions
 *
 * @property GridField|$this $owner
 */
class GridFieldURLListener extends Extension
{

    /**
     * Extension point in @see GridField::handleRequest
     * GridField action via custom URL handler
     * covers action which are implemented via @see GridField_URLHandler
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $result
     */
    public function afterCallActionURLHandler(HTTPRequest $request, $action, $result): void {
        Dispatcher::singleton()->trigger(
            'gridFieldAction',
            new GridFieldContext($action, $request, $result, $this->owner)
        );
    }
}
