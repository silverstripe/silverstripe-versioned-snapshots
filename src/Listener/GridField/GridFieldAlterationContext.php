<?php


namespace SilverStripe\Snapshots\Listener\GridField;


use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\FormAction\StateStore;
use SilverStripe\Forms\GridField\GridField;

class GridFieldAlterationContext extends GridFieldContext
{
    public function getAction(): string
    {
        $actionData = $this->getActionData(
            $this->getRequest()->requestVars(),
            $this->getGridField()
        );
        if (!$actionData) {
            return null;
        }

        return array_shift($actionData);
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
