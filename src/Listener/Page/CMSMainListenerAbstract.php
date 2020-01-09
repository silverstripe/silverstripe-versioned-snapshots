<?php

namespace SilverStripe\Snapshots\Listener\Page;

use Page;
use SilverStripe\Core\Extension;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class CMSMainListenerAbstract
 *
 * @package SilverStripe\Snapshots\Listener\Page
 */
abstract class CMSMainListenerAbstract extends Extension
{
    /**
     * Extension point in @see Snapshot::CMSMainActionSnapshot()
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @return bool
     */
    public function overrideCMSMainActionSnapshot(
        Page $page,
        string $action,
        string $message
    ): bool {
        if ($action !== $this->getActionName()) {
            return false;
        }

        return $this->processAction($page, $action, $message);
    }

    abstract protected function getActionName(): string;

    abstract protected function processAction(
        Page $page,
        string $action,
        string $message
    ): bool;
}
