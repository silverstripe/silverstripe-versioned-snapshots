<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Snapshots\Dispatch\Context;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Listener\CurrentPage;

class FormSubmissionHandler extends HandlerAbstract implements HandlerInterface
{

    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $formHandlerName;

    /**
     * @var string
     */
    protected $formName = 'EditForm';

    /**
     * @var string
     */
    protected $controllerClass = LeftAndMain::class;

    /**
     * FormSubmissionSnapshotHandler constructor.
     * @param string $message
     * @param string $formHandlerName
     * @param string $formName
     * @param string $controllerClass
     */
    public function __construct(
        string $message,
        string $formHandlerName,
        string $formName = 'EditForm',
        string $controllerClass = LeftAndMain::class
    ) {
        $this->message = $message;
        $this->formHandlerName = $formHandlerName;
        $this->formName = $formName;
        $this->controllerClass = $controllerClass;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param Context $context
     * @return bool
     */
    public function shouldFire(Context $context): bool
    {
        return (
            parent::shouldFire($context) &&
            $context->form->getController() instanceof $this->controllerClass &&
            $context->form->getName() === $this->formName &&
            $context->handlerName == $this->formHandlerName
        );
    }

    /**
     * @param Context $context
     */
    public function fire(Context $context): void
    {
        $message = $this->getMessage();
        $record = $context->form->getRecord();

        if ($record === null) {
            return;
        }

        $url = $context->request->getURL();
        $page = $this->getCurrentPageFromRequestUrl($url);

        if ($page === null) {
            return;
        }

        $snapshot->createSnapshotFromAction($page, $record, $message);
    }
}
