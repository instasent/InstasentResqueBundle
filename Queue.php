<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

class Queue
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getSize()
    {
        return \Resque::size($this->name);
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @param int $start
     * @param int $stop
     *
     * @throws \Resque_Exception
     *
     * @return mixed[]
     */
    public function getJobs(int $start = 0, int $stop = -1): array
    {
        $jobs = \Resque::redis()->lrange('queue:'.$this->name, $start, $stop);

        $result = [];
        foreach ($jobs as $job) {
            $job = new \Resque_Job($this->name, \json_decode($job, true));
            $result[] = $job->getInstance();
        }

        return $result;
    }
}
