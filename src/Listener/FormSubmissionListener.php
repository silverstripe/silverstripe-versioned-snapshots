<?php

namespace SilverStripe\Snapshots\Listener;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormRequestHandler;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Dispatch\Dispatcher;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\Listener\CurrentPage;

/**
 * Class Submission
 *
 * Snapshot action listener for form submissions
 *
 * @property FormRequestHandler|$this $owner
 * @package SilverStripe\Snapshots\Listener
 */
class FormSubmissionListener extends Extension
{

    use CurrentPage;

    /**
     * Extension point in @see FormRequestHandler::httpSubmission
     * controller action via form submission action
     *
     * @param HTTPRequest $request
     * @param $funcName
     * @param $vars
     * @param Form $form
     * @throws ValidationException
     */
    public function afterCallFormHandler (HTTPRequest $request, $funcName, $vars, $form): void
    {
        Dispatcher::singleton()->trigger('formSubmitted', new Context([
            'handlerName' => $funcName,
            'form' => $form,
            'request' => $request
        ]);
    }
}
