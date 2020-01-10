<?php


namespace SilverStripe\Snapshots\Listener;


abstract class EventContext
{
    abstract public function getAction(): string;

    /**
     * @param $name
     * @return |null
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return null;
    }
}
