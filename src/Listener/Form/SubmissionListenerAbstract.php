<?php

namespace SilverStripe\Snapshots\Listener\Form;

use Page;
use SilverStripe\Core\Extension;
use SilverStripe\Snapshots\Snapshot;

/**
 * Class SubmissionListenerAbstract
 *
 * @package SilverStripe\Snapshots\Listener\Form
 */
abstract class SubmissionListenerAbstract extends Extension
{
    /**
     * Extension point in @see Snapshot::formSubmissionSnapshot()
     *
     * @param Form $form
     * @param HTTPRequest $request
     * @param Page $page
     * @param string $action
     * @param string $message
     * @return bool
     */
    public function overrideFormSubmissionSnapshot(
        Form $form,
        HTTPRequest $request,
        Page $page,
        string $action,
        string $message
    ): bool {
        if ($action !== $this->getActionName()) {
            return false;
        }

        return $this->processAction($form, $request, $page, $action, $message);
    }

    abstract protected function getActionName(): string;

    abstract protected function processAction(
        Form $form,
        HTTPRequest $request,
        Page $page,
        string $action,
        string $message
    ): bool;
}
