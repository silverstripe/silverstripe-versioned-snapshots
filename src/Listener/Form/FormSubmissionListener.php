<?php

namespace SilverStripe\Snapshots\Listener\Form;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\Snapshots\Dispatch\Dispatcher;

/**
 * Class Submission
 *
 * Snapshot action listener for form submissions
 *
 * @property FormRequestHandler|$this $owner
 */
class FormSubmissionListener extends Extension
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
    public function afterCallFormHandler (HTTPRequest $request, $funcName, $vars, $form): void
    {
        Dispatcher::singleton()->trigger('formSubmitted', new FormContext($funcName, $form, $request, $vars));
    }
}
