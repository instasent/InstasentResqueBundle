<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

class FailedJob
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @param array $data Contains the failed job data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getFailedAt()
    {
        return $this->data['failed_at'];
    }

    public function getName()
    {
        return $this->data['payload']['class'];
    }

    public function getId()
    {
        return $this->data['payload']['id'];
    }

    public function getQueueName()
    {
        return $this->data['queue'];
    }

    public function getWorkerName()
    {
        return $this->data['worker'];
    }

    public function getArgs()
    {
        return $this->data['payload']['args'];
    }

    public function getExceptionClass()
    {
        return $this->data['exception'];
    }

    public function getError()
    {
        return $this->data['error'];
    }

    public function getBacktrace()
    {
        return $this->data['backtrace'];
    }
}
