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
 * Class AlterAction
 *
 * Snapshot action listener for grid field actions
 *
 * @property GridField|$this $owner
 * @package SilverStripe\Snapshots\Listener\GridField
 */
class GridFieldAlterationListener extends Extension
{

    use CurrentPage;

    /**
     * Extension point in @see GridField::handleAction
     * GridField action via GridField alter action
     * covers actions which are implemented via @see GridField_ActionProvider
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $result
     * @throws ValidationException
     */
    public function afterCallActionHandler( // phpcs:ignore SlevomatCodingStandard.TypeHints
        HTTPRequest $request,
        $action,
        $result
    ): void {
        Dispatcher::singleton()->trigger('gridFieldAlteration', new Context([
            'action' => $action,
            'request' => $request,
            'result' => $result,
            'gridField' => $this->owner,
        ]));
    }

}
