<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

abstract class Job implements JobInterface
{
    /**
     * @var \Resque_Job
     */
    public $job;

    /**
     * @var string The queue name
     */
    public $queue = 'default';

    /**
     * @var mixed[] The job args
     */
    public $args = [];

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return \get_class($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments()
    {
        return $this->args;
    }

    /**
     * {@inheritdoc}
     */
    public function hasArgument($arg)
    {
        return isset($this->args[$arg]);
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument($arg)
    {
        return isset($this->args[$arg]) ? $this->args[$arg] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setArgument($arg, $value)
    {
        $this->args[$arg] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function perform()
    {
        $this->run($this->args);
    }

    abstract public function run($args);

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        // noop
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        // noop
    }
}
