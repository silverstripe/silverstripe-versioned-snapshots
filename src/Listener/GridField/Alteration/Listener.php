<?php

namespace SilverStripe\Snapshots\Listener\GridField\Alteration;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\FormAction\StateStore;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Listener\EventContext;

/**
 * Class AlterAction
 *
 * Snapshot action listener for grid field actions
 *
 * @property GridField|$this $owner
 */
class Listener extends Extension
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
    public function afterCallActionHandler(HTTPRequest $request, $action, $result): void
    {
        if (!in_array($action, ['index', 'gridFieldAlterAction'])) {
            return;
        }
        $actionName = null;
        $arguments = [];
        $actionData = $this->getActionData($request->requestVars(), $this->owner);
        if ($actionData) {
            list ($actionName, $arguments) = $actionData;
        }
        if (!$actionName === null) {
            return;
        }
        Dispatcher::singleton()->trigger(
            'gridFieldAlteration',
            new EventContext(
                $actionName,
                [
                    'request' => $request,
                    'result' => $result,
                    'gridField' => $this->owner,
                    'args' => $arguments,
                ]
            )
        );
    }

    /**
     * @param array $data
     * @param GridField $gridField
     * @return array|null
     */
    private function getActionData(array $data, GridField $gridField): ?array
    {
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
