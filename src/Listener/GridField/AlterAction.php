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
 * Class AlterAction
 *
 * Snapshot action listener for grid field actions
 *
 * @property GridField|$this $owner
 * @package SilverStripe\Snapshots\Listener\GridField
 */
class AlterAction extends Extension
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
        if (!in_array($action, ['index', 'gridFieldAlterAction'])) {
            return;
        }

        $owner = $this->owner;
        $snapshot = Snapshot::singleton();

        if (!$snapshot->isActionTriggerActive()) {
            return;
        }

        $requestData = $request->requestVars();
        $actionData = $this->getActionData($requestData);

        if ($actionData === null) {
            return;
        }

        $identifier = array_shift($actionData);
        $arguments = array_shift($actionData);
        $data = array_shift($actionData);

        $message = $snapshot->getActionMessage($identifier);

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
        $customSnapshot = $snapshot->gridFieldAlterActionSnapshot(
            $page,
            $identifier,
            $message,
            $owner,
            $arguments,
            $data
        );

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, $record, $message);
    }

    private function getActionData(array $data): ?array
    {
        $gridField = $this->owner;

        // Fetch the store for the "state" of actions (not the GridField)
        /** @var StateStore $store */
        $store = Injector::inst()->create(StateStore::class . '.' . $gridField->getName());

        foreach ($data as $dataKey => $dataValue) {
            if (!preg_match('/^action_gridFieldAlterAction\?StateID=(.*)/', $dataKey, $matches)) {
                continue;
            }

            $stateChange = $store->load($matches[1]);

            $actionName = $stateChange['actionName'];
            $arguments = array_key_exists('args', $stateChange) ? $stateChange['args'] : [];
            $arguments = is_array($arguments) ? $arguments : [];

            if ($actionName) {
                return [
                    $actionName,
                    $arguments,
                    $data,
                ];
            }
        }

        return null;
    }
}
