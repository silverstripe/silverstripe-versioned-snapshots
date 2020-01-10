<?php


namespace SilverStripe\Snapshots\Listener\CMSMain;


use SilverStripe\Snapshots\Listener\EventContext;

class CMSMainContext extends EventContext
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var null
     */
    private $result;

    /**
     * @var string|null
     */
    private $treeClass;

    /**
     * @var string|null
     */
    private $id;

    /**
     * @param string $action
     * @param null $result
     * @param string|null $treeClass
     * @param string|null $id
     */
    public function __construct(string $action, $result = null, ?string $treeClass = null, ?string $id = null)
    {
        $this->action = $action;
        $this->result = $result;
        $this->treeClass = $treeClass;
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return string|null
     */
    public function getTreeClass(): ?string
    {
        return $this->treeClass;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }
}
