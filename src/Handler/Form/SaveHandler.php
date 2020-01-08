<?php


namespace SilverStripe\Snapshots\Handler\Form;


use SilverStripe\Snapshots\Dispatch\Context;

class SaveHandler extends FormSubmissionHandler
{
    /**
     * @var string
     */
    protected $formHandlerName = 'save';

    /**
     * @return string
     */
    protected function getMessage(): string
    {
        return _t('Snapshots.HANDLER_SAVE', 'Save page');
    }

    /**
     * @param Context $context
     * @return bool
     */
    public function shouldFire(Context $context): bool
    {
        $url = $context->request->getURL();
        $page = $this->getCurrentPageFromRequestUrl($url);

        return parent::shouldFire($context) && $page && $page->isModifiedOnDraft();
    }
}
