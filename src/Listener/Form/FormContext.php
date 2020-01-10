<?php


namespace SilverStripe\Snapshots\Listener\Form;


use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\Form;
use SilverStripe\Snapshots\Listener\ListenerContext;

class FormContext extends ListenerContext
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var Form|null
     */
    private $form;

    /**
     * @var HTTPRequest|null
     */
    private $request;

    /**
     * @var array
     */
    private $vars = [];

    /**
     * FormContext constructor.
     * @param string $action
     * @param Form|null $form
     * @param HTTPRequest|null $request
     * @param array $vars
     */
    public function __construct(string $action, ?Form $form = null, ?HTTPRequest $request = null, array $vars = [])
    {
        $this->action = $action;
        $this->form = $form;
        $this->request = $request;
        $this->vars = $vars;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return Form|null
     */
    public function getForm(): ?Form
    {
        return $this->form;
    }

    /**
     * @return HTTPRequest|null
     */
    public function getRequest(): ?HTTPRequest
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getVars(): array
    {
        return $this->vars;
    }


}
