<?php

namespace SilverStripe\Snapshots\Listener\Page;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class CMSMainAction
 *
 * Snapshot action listener for CMS main actions
 *
 * @property CMSMain|$this $owner
 * @package SilverStripe\Snapshots\Listener\Page
 */
class CMSMainAction extends Extension
{
    /**
     * Extension point in @see CMSMain::handleAction
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
        $snapshot = Snapshot::singleton();

        if (!$snapshot->isActionTriggerActive()) {
            return;
        }

        $message = $snapshot->getActionMessage($action);

        if ($message === null) {
            return;
        }

        if (!$result instanceof HTTPResponse) {
            return;
        }

        if ((int) $result->getStatusCode() !== 200) {
            return;
        }

        $className = $this->owner->config()->get('tree_class');
        $id = (int) $request->requestVar('ID');

        if (!$id) {
            return;
        }

        /** @var SiteTree $page */
        $page = DataObject::get_by_id($className, $id);

        if ($page === null) {
            return;
        }

        // attempt to create a custom snapshot first
        $customSnapshot = $snapshot->CMSMainActionSnapshot($page, $action, $message);

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, null, $message);
    }
}
