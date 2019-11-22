<?php

namespace SilverStripe\Snapshots\Listener\GridField;

use Page;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class UrlHandlerActionListenerAbstract
 *
 * @package SilverStripe\Snapshots\Listener\GridField
 */
abstract class UrlHandlerActionListenerAbstract extends Extension
{
    /**
     * Extension point in @see Snapshot::gridFieldUrlActionSnapshot()
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param GridField $gridField
     * @return bool
     */
    public function overrideGridFieldUrlActionSnapshot(
        Page $page,
        string $action,
        string $message,
        GridField $gridField
    ): bool {
        if ($action !== $this->getActionName()) {
            return false;
        }

        return $this->processAction($page, $action, $message, $gridField);
    }

    abstract protected function getActionName(): string;

    abstract protected function processAction(
        Page $page,
        string $action,
        string $message,
        GridField $gridField
    ): bool;
}
