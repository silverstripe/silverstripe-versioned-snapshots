<?php

namespace SilverStripe\Snapshots\Listener\GridField;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\FormAction\StateStore;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\ValidationException;
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
class UrlHandlerAction extends Extension
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
        $owner = $this->owner;
        $snapshot = Snapshot::singleton();

        if (!$snapshot->isActionTriggerActive()) {
            return;
        }

        $message = $snapshot->getActionMessage($action);

        if ($message === null) {
            return;
        }

        $form = $owner->getForm();

        if (!$form) {
            return;
        }

        $record = $form->getRecord();

        if (!$record) {
            return;
        }

        $page = $this->getCurrentPageFromController($form);

        if ($page === null) {
            return;
        }

        // attempt to create a custom snapshot first
        $customSnapshot = $snapshot->gridFieldUrlActionSnapshot($page, $action, $message, $owner);

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, $record, $message);
    }
}
