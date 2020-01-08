<?php


namespace SilverStripe\Snapshots\Dispatch;


class Context
{
    private $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function __get(string $name)
    {
        return $this->get(name);
    }

    public function get(string $name)
    {
        return $this->data[$name] ?? null;
    }
}
