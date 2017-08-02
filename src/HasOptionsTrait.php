<?php

namespace MadeSimple\TaskWorker;

/**
 * Class HasOptions
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 *
 * @method static array defaultOptions()
 */
trait HasOptionsTrait
{
    /**
     * @var array
     */
    private $options = [];

    /**
     * @param array $options
     *
     * @return static
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * @param string $option
     * @param mixed  $value
     *
     * @return static
     */
    public function setOption(string $option, $value)
    {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * @param string $option
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function opt(string $option, $default = null)
    {
        return $this->options[$option] ?? $default;
    }
}