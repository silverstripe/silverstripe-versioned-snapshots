<?php

namespace SilverStripe\Snapshots\Listener\Form;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Listener\CurrentPage;

/**
 * Class Submission
 *
 * Snapshot action listener for form submissions
 *
 * @property FormRequestHandler|$this $owner
 * @package SilverStripe\Snapshots\Listener\Form
 */
class Submission extends Extension
{

    use CurrentPage;

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * controller action via form submission action
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $vars
     * @param Form $form
     * @param $result
     * @throws ValidationException
     */
    public function afterCallFormHandlerController( // phpcs:ignore SlevomatCodingStandard.TypeHints
        HTTPRequest $request,
        $action,
        $vars,
        $form,
        $result
    ): void {
        $this->processAction($action, $form, $request);
    }

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * form handler action via form submission action
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $vars
     * @param $form
     * @param $result
     * @throws ValidationException
     */
    public function afterCallFormHandlerMethod( // phpcs:ignore SlevomatCodingStandard.TypeHints
        HTTPRequest $request,
        $action,
        $vars,
        $form,
        $result
    ): void {
        $this->processAction($action, $form, $request);
    }

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * form method action via form submission action
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $vars
     * @param $form
     * @param $result
     * @throws ValidationException
     */
    public function afterCallFormHandlerFormMethod( // phpcs:ignore SlevomatCodingStandard.TypeHints
        HTTPRequest $request,
        $action,
        $vars,
        $form,
        $result
    ): void {
        $this->processAction($action, $form, $request);
    }

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * form field method action via form submission action
     *
     * @param HTTPRequest $request
     * @param $action
     * @param $vars
     * @param $form
     * @param $result
     * @throws ValidationException
     */
    public function afterCallFormHandlerFieldMethod( // phpcs:ignore SlevomatCodingStandard.TypeHints
        HTTPRequest $request,
        $action,
        $vars,
        $form,
        $result
    ): void {
        $this->processAction($action, $form, $request);
    }

    /**
     * @param string|null $action
     * @param Form $form
     * @param HTTPRequest $request
     * @throws ValidationException
     */
    private function processAction(?string $action, Form $form, HTTPRequest $request): void
    {
        $snapshot = Snapshot::singleton();

        if (!$snapshot->isActionTriggerActive()) {
            return;
        }

        $message = $snapshot->getActionMessage($action);

        if ($message === null) {
            return;
        }

        $record = $form->getRecord();

        if ($record === null) {
            return;
        }

        $url = $request->getURL();
        $page = $this->getCurrentPageFromRequestUrl($url);

        if ($page === null) {
            return;
        }

        // avoid recording useless save actions to prevent multiple snapshots of the same version
        if ($form->getName() === 'EditForm' && $action === 'save' && !$page->isModifiedOnDraft()) {
            return;
        }

        // attempt to create a custom snapshot first
        $customSnapshot = $snapshot->formSubmissionSnapshot($form, $request, $page, $action, $message);

        if ($customSnapshot) {
            return;
        }

        // fall back to default snapshot
        $snapshot->createSnapshotFromAction($page, $record, $message);
    }
}
