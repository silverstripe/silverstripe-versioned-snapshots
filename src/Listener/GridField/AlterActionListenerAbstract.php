<?php

namespace SilverStripe\Snapshots\Listener\GridField;

use Page;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class AlterActionListenerAbstract
 *
 * @package SilverStripe\Snapshots\Listener\GridField
 */
abstract class AlterActionListenerAbstract extends Extension
{
    /**
     * Extension point in @see Snapshot::gridFieldAlterActionSnapshot()
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param GridField $gridField
     * @param array $arguments
     * @param array $data
     * @return bool
     */
    public function overrideGridFieldAlterActionSnapshot(
        Page $page,
        string $action,
        string $message,
        GridField $gridField,
        array $arguments,
        array $data
    ): bool {
        if ($action !== $this->getActionName()) {
            return false;
        }

        return $this->processAction($page, $action, $message, $gridField, $arguments, $data);
    }

    abstract protected function getActionName(): string;

    abstract protected function processAction(
        Page $page,
        string $action,
        string $message,
        GridField $gridField,
        array $arguments,
        array $data
    ): bool;
}
