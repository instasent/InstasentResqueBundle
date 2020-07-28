<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

use Psr\Log\NullLogger;

class Resque
{
    /**
     * @var array
     */
    private $kernelOptions;

    /**
     * @var array
     */
    private $redisConfiguration;

    /**
     * @var array
     */
    private $globalRetryStrategy = [];

    /**
     * @var array
     */
    private $jobRetryStrategy = [];

    public function __construct(array $kernelOptions)
    {
        $this->kernelOptions = $kernelOptions;
    }

    public function setPrefix($prefix)
    {
        \Resque_Redis::prefix($prefix);
    }

    public function setRedisConfiguration($host, $port, $database)
    {
        $this->redisConfiguration = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
        ];
        $host = \substr($host, 0, 1) === '/' ? $host : $host.':'.$port;

        \Resque::setBackend($host, $database);
    }

    public function setGlobalRetryStrategy($strategy)
    {
        $this->globalRetryStrategy = $strategy;
    }

    public function setJobRetryStrategy($strategy)
    {
        $this->jobRetryStrategy = $strategy;
    }

    public function getRedisConfiguration()
    {
        return $this->redisConfiguration;
    }

    public function enqueue(JobInterface $job, $trackStatus = false)
    {
        if ($job instanceof ContainerAwareJobInterface) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        $result = \Resque::enqueue($job->getQueue(), \get_class($job), $job->getArguments(), $trackStatus);

        if ($trackStatus) {
            return new \Resque_Job_Status($result);
        }
    }

    public function enqueueOnce(JobInterface $job, $trackStatus = false)
    {
        $queue = new Queue($job->getQueue());
        $jobs = $queue->getJobs();

        foreach ($jobs as $j) {
            if ($j->job->payload['class'] === \get_class($job)) {
                if (\count(\array_intersect($j->args, $job->getArguments())) === \count($job->getArguments())) {
                    return $trackStatus ? $j->job->payload['id'] : null;
                }
            }
        }

        return $this->enqueue($job, $trackStatus);
    }

    public function enqueueAt($at, JobInterface $job)
    {
        if ($job instanceof ContainerAwareJobInterface) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        \ResqueScheduler::enqueueAt($at, $job->getQueue(), \get_class($job), $job->getArguments());
    }

    public function enqueueIn($in, JobInterface $job)
    {
        if ($job instanceof ContainerAwareJobInterface) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        \ResqueScheduler::enqueueIn($in, $job->getQueue(), \get_class($job), $job->getArguments());
    }

    public function removedDelayed(JobInterface $job)
    {
        if ($job instanceof ContainerAwareJobInterface) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayed($job->getQueue(), \get_class($job), $job->getArguments());
    }

    public function removeFromTimestamp($at, JobInterface $job)
    {
        if ($job instanceof ContainerAwareJobInterface) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayedJobFromTimestamp(
            $at,
            $job->getQueue(),
            \get_class($job),
            $job->getArguments()
        );
    }

    public function getQueues()
    {
        return \array_map(function ($queue) {
            return new Queue($queue);
        }, \Resque::queues());
    }

    /**
     * Returns an array of queues with its current load.
     *
     * @param mixed|null $pattern
     *
     * @return array
     */
    public function getQueuesLoad($pattern = null)
    {
        $queues = [];

        foreach (\Resque::queues() as $queue) {
            $queues[$queue] = \Resque::size($queue);
        }

        return $queues;
    }

    /**
     * @param $queueKey
     * @param $parallelQueues
     *
     * @return array of queues with its loads
     */
    public function getParallelQueueLoads($queueKey, $parallelQueues)
    {
        for ($i = 1; $i <= $parallelQueues; ++$i) {
            $queues[$queueKey.'-'.$i] = \Resque::size($queueKey.'-'.$i);
        }

        return $queues;
    }

    /**
     * @param mixed $queueKey
     * @param mixed $parallelQueues
     *
     * @return string The queue name with less job load
     */
    public function getLessLoadedParallelQueue($queueKey, $parallelQueues)
    {
        $queues = $this->getParallelQueueLoads($queueKey, $parallelQueues);
        \asort($queues);
        $lessLoadedQueue = \array_slice($queues, 0, 1);

        return \key($lessLoadedQueue);
    }

    /**
     * @param $queue
     *
     * @return Queue
     */
    public function getQueue($queue)
    {
        return new Queue($queue);
    }

    public function getWorkers()
    {
        return \array_map(function ($worker) {
            return new Worker($worker);
        }, \Resque_Worker::all());
    }

    public function getWorker($id)
    {
        $worker = \Resque_Worker::find($id);

        if ($worker) {
            return new Worker($worker);
        }
    }

    public function pruneDeadWorkers()
    {
        // HACK, prune dead workers, just in case
        $worker = new \Resque_Worker('temp');
        $worker->setLogger(new NullLogger());
        $worker->pruneDeadWorkers();
    }

    public function getDelayedJobTimestamps()
    {
        $timestamps = \Resque::redis()->zrange('delayed_queue_schedule', 0, -1);

        //TODO: find a more efficient way to do this
        $out = [];
        foreach ($timestamps as $timestamp) {
            $out[] = [$timestamp, \Resque::redis()->llen('delayed:'.$timestamp)];
        }

        return $out;
    }

    public function getFirstDelayedJobTimestamp()
    {
        $timestamps = $this->getDelayedJobTimestamps();
        if (\count($timestamps) > 0) {
            return $timestamps[0];
        }

        return [null, 0];
    }

    public function getNumberOfDelayedJobs()
    {
        return \ResqueScheduler::getDelayedQueueScheduleSize();
    }

    public function getJobsForTimestamp($timestamp)
    {
        $jobs = \Resque::redis()->lrange('delayed:'.$timestamp, 0, -1);
        $out = [];
        foreach ($jobs as $job) {
            $out[] = \json_decode($job, true);
        }

        return $out;
    }

    /**
     * @param $queue
     *
     * @return int
     */
    public function clearQueue($queue)
    {
        $length = \Resque::redis()->llen('queue:'.$queue);
        \Resque::redis()->del('queue:'.$queue);

        return $length;
    }

    public function getFailedJobs($start = -100, $count = 100)
    {
        $jobs = \Resque::redis()->lrange('failed', $start, $count);

        $result = [];

        foreach ($jobs as $job) {
            $result[] = new FailedJob(\json_decode($job, true));
        }

        return $result;
    }

    /**
     * Attach any applicable retry strategy to the job.
     *
     * @param JobInterface $job
     */
    protected function attachRetryStrategy(JobInterface $job)
    {
        $class = \get_class($job);

        if (isset($this->jobRetryStrategy[$class]) && \count($this->jobRetryStrategy[$class])) {
            $job->setArgument('instasent_resque.retry_strategy', $this->jobRetryStrategy[$class]);
        } elseif (\count($this->globalRetryStrategy)) {
            $job->setArgument('instasent_resque.retry_strategy', $this->globalRetryStrategy);
        }
    }
}
