<?php


namespace SilverStripe\Snapshots\Listener;


use phpDocumentor\Reflection\Types\Scalar;

class EventContext
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var array
     */
    private $meta = [];

    /**
     * EventContext constructor.
     * @param string $action
     * @param array $meta
     */
    public function __construct(string $action, array $meta = [])
    {
        $this->action = $action;
        $this->meta = $meta;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param string $name
     * @return string|int|bool|float|null
     */
    public function get(string $name)
    {
        return $this->meta[$name] ?? null;
    }

    /**
     * @param $name
     * @return string|int|bool|float|null
     */
    public function __get($name)
    {
        return $this->get($name);
    }

}
