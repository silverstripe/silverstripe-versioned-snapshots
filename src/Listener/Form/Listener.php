<?php

namespace SilverStripe\Snapshots\Listener\Form;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Listener\EventContext;

/**
 * Class Submission
 *
 * Snapshot action listener for form submissions
 * This covers all extension points related to form submissions
 * extension points are mutually exclusive (at most one of the extension points is used in one form submission)
 * each extension point represents a different way how form submission is processed
 * all of these extension points are necessary to cover all cases of form submissions
 * we could use a more generic extension point to cover all cases with just one extension point
 * however this would force us to duplicate functionality which extracts context information
 * as such information is not provided in the more general extension point
 *
 * @property FormRequestHandler|$this $owner
 */
class Listener extends Extension
{
    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * controller action via form submission action
     *
     * @param HTTPRequest $request
     * @param $funcName
     * @param $vars
     * @param Form $form
     */
    public function afterCallFormHandlerController(HTTPRequest $request, $funcName, $vars, $form): void
    {
        $this->triggerAction($request, $funcName, $vars, $form);
    }

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * form handler action via form submission action
     *
     * @param HTTPRequest $request
     * @param $funcName
     * @param $vars
     * @param Form $form
     */
    public function afterCallFormHandlerMethod(HTTPRequest $request, $funcName, $vars, $form): void
    {
        $this->triggerAction($request, $funcName, $vars, $form);
    }

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * form method action via form submission action
     *
     * @param HTTPRequest $request
     * @param $funcName
     * @param $vars
     * @param Form $form
     */
    public function afterCallFormHandlerFormMethod(HTTPRequest $request, $funcName, $vars, $form): void
    {
        $this->triggerAction($request, $funcName, $vars, $form);
    }

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * form field method action via form submission action
     *
     * @param HTTPRequest $request
     * @param $funcName
     * @param $vars
     * @param Form $form
     */
    public function afterCallFormHandlerFieldMethod(HTTPRequest $request, $funcName, $vars, $form): void
    {
        $this->triggerAction($request, $funcName, $vars, $form);
    }

    /**
     * @param HTTPRequest $request
     * @param $funcName
     * @param $vars
     * @param $form
     */
    private function triggerAction(HTTPRequest $request, $funcName, $vars, $form): void
    {
        Dispatcher::singleton()->trigger(
            'formSubmitted',
            new EventContext(
                $funcName,
                [
                    'form' => $form,
                    'request' => $request,
                    'vars' => $vars
                ]
            )
        );
    }
}
