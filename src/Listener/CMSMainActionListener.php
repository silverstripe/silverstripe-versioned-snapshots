<?php

namespace SilverStripe\Snapshots\Listener;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class CMSMainAction
 *
 * Snapshot action listener for CMS main actions
 *
 * @property CMSMain|$this $owner
 * @package SilverStripe\Snapshots\Listener\Page
 */
class CMSMainActionListener extends Extension
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
        Dispatcher::singleton()->trigger('cmsAction', new Context([
            'action' => $action,
            'result' => $result,
            'treeClass' => $this->owner->config()->get('tree_class'),
            'id' => $request->requestVar('ID'),
        ]));
    }
}
