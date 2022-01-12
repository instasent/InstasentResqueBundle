<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopScheduledWorkerCommand extends ContainerAwareCommand
{
    /**
     * Command name.
     */
    const NAME = 'instasent:resque:scheduledworker-stop';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Stop a instasent resque scheduled worker');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pidFile = $this->getContainer()->get('kernel')->getCacheDir().'/instasent_resque_scheduledworker.pid';
        if (!\file_exists($pidFile)) {
            $output->writeln('No PID file found');

            return 1;
        }

        $pid = \file_get_contents($pidFile);

        $output->writeln('Killing process '.$pid);

        \posix_kill($pid, \SIGTERM);

        \unlink($pidFile);

        return 0;
    }
}
