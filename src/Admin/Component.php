<?php
namespace Core\Admin;


abstract class Component
{
    public function __construct($component)
    {
        $this->component = $component;
    }
    protected function make()
    {
        return $this;
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }
}