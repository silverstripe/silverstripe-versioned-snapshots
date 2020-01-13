<?php


namespace SilverStripe\Snapshots\Listener;

class EventContext
{
    /**
     * @var string|null
     */
    private $action;

    /**
     * @var array
     */
    private $meta = [];

    /**
     * EventContext constructor.
     * @param string|null $action
     * @param array $meta
     */
    public function __construct(?string $action = null, array $meta = [])
    {
        $this->action = $action;
        $this->meta = $meta;
    }

    /**
     * @return string|null
     */
    public function getAction(): ?string
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
