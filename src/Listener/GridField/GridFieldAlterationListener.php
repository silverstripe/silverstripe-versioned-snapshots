<?php

namespace SilverStripe\Snapshots\Listener\GridField;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Dispatch\Dispatcher;

/**
 * Class AlterAction
 *
 * Snapshot action listener for grid field actions
 *
 * @property GridField|$this $owner
 */
class GridFieldAlterationListener extends Extension
{

    /**
     * Extension point in @see GridField::handleAction
     * GridField action via GridField alter action
     * covers actions which are implemented via @see GridField_ActionProvider
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $result
     */
    public function afterCallActionHandler(HTTPRequest $request, $action, $result): void {
        if (!in_array($action, ['index', 'gridFieldAlterAction'])) {
            return;
        }
        Dispatcher::singleton()->trigger(
            'gridFieldAlteration',
            new GridFieldAlterationContext($action, $request, $result, $this->owner)
        );
    }

}
