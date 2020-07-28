<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $this->getResque()->pruneDeadWorkers();

        return $this->render(
            'InstasentResqueBundle:Default:index.html.twig',
            [
                'resque' => $this->getResque(),
            ]
        );
    }

    public function showQueueAction(Request $request)
    {
        list($start, $count, $showingAll) = $this->getShowParameters($request);

        $queueName = $request->get('queue');
        $queue = $this->getResque()->getQueue($queueName);
        $jobs = $queue->getJobs($start, $count);

        if (!$showingAll) {
            $jobs = \array_reverse($jobs);
        }

        return $this->render(
            'InstasentResqueBundle:Default:queue_show.html.twig',
            [
                'queue' => $queue,
                'jobs' => $jobs,
                'showingAll' => $showingAll,
            ]
        );
    }

    public function listFailedAction(Request $request)
    {
        list($start, $count, $showingAll) = $this->getShowParameters($request);

        $jobs = $this->getResque()->getFailedJobs($start, $count);

        if (!$showingAll) {
            $jobs = \array_reverse($jobs);
        }

        return $this->render(
            'InstasentResqueBundle:Default:failed_list.html.twig',
            [
                'jobs' => $jobs,
                'showingAll' => $showingAll,
            ]
        );
    }

    public function listScheduledAction()
    {
        return $this->render(
            'InstasentResqueBundle:Default:scheduled_list.html.twig',
            [
                'timestamps' => $this->getResque()->getDelayedJobTimestamps(),
            ]
        );
    }

    public function showTimestampAction($timestamp)
    {
        $jobs = [];

        // we don't want to enable the twig debug extension for this...
        foreach ($this->getResque()->getJobsForTimestamp($timestamp) as $job) {
            $jobs[] = \print_r($job, true);
        }

        return $this->render(
            'InstasentResqueBundle:Default:scheduled_timestamp.html.twig',
            [
                'timestamp' => $timestamp,
                'jobs' => $jobs,
            ]
        );
    }

    /**
     * @return \Instasent\ResqueBundle\Resque
     */
    protected function getResque()
    {
        return $this->get('instasent_resque.resque');
    }

    /**
     * decide which parts of a job queue to show.
     *
     * @param Request $request
     *
     * @return array
     */
    private function getShowParameters(Request $request)
    {
        $showingAll = false;
        $start = -100;
        $count = -1;

        if ($request->query->has('all')) {
            $start = 0;
            $count = -1;
            $showingAll = true;
        }

        return [$start, $count, $showingAll];
    }
}
