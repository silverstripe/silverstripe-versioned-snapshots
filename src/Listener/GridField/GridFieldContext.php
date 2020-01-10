<?php


namespace SilverStripe\Snapshots\Listener\GridField;


use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Listener\ListenerContext;

class GridFieldContext extends ListenerContext
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var HTTPRequest|null
     */
    private $request;

    /**
     * @var null
     */
    private $result;

    /**
     * @var GridField|null
     */
    private $gridField;

    /**
     * GridFieldAlterationContext constructor.
     * @param string $action
     * @param HTTPRequest|null $request
     * @param null $result
     * @param GridField|null $gridField
     */
    public function __construct(string $action, ?HTTPRequest $request = null, $result = null, ?GridField $gridField = null)
    {
        $this->action = $action;
        $this->request = $request;
        $this->result = $result;
        $this->gridField = $gridField;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return HTTPRequest|null
     */
    public function getRequest(): ?HTTPRequest
    {
        return $this->request;
    }

    /**
     * @return null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return GridField|null
     */
    public function getGridField(): ?GridField
    {
        return $this->gridField;
    }


}
