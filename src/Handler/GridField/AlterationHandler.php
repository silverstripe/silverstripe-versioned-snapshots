<?php


namespace SilverStripe\Snapshots\Handler\GridField;


use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\FormAction\StateStore;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Handler\HandlerInterface;
use SilverStripe\Snapshots\Listener\CurrentPage;
use SilverStripe\Snapshots\Snapshot;

class AlterationHandler extends HandlerAbstract implements HandlerInterface
{

    /**
     * @param Context $context
     * @return bool
     */
    public function shouldFire(Context $context): bool
    {
        return (
            parent::shouldFire($context) &&
            in_array($action, ['index', 'gridFieldAlterAction'])
        );
    }

    public function fire(Context $context): void
    {
        $requestData = $request->requestVars();
        $actionData = $this->getActionData($requestData, $context->gridField);

        if ($actionData === null) {
            return;
        }

        list ($identifier, $arguments, $data) = $actionData;

        $message = $this->getMessage();
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

        $snapshot->createSnapshotFromAction($page, $record, $message);
    }

    /**
     * @param array $data
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
