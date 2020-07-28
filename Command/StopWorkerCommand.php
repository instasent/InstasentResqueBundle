<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StopWorkerCommand extends ContainerAwareCommand
{
    /**
     * Command name.
     */
    const NAME = 'instasent:resque:worker-stop';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Stop a instasent resque worker')
            ->addArgument('id', InputArgument::OPTIONAL, 'Worker id')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Should kill all workers');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Instasent\ResqueBundle\Resque $resque */
        $resque = $this->getContainer()->get('instasent_resque.resque');

        if (\trim($input->getOption('all')) !== '') {
            $workers = $resque->getWorkers();
        } else {
            $worker = $resque->getWorker($input->getArgument('id'));

            if (!$worker) {
                $availableWorkers = $resque->getWorkers();
                if (!empty($availableWorkers)) {
                    $output->writeln('<error>You need to provide an existing worker.</error>');
                    $output->writeln('Running workers are:');

                    foreach ($availableWorkers as $worker) {
                        $output->writeln($worker->getId());
                    }
                } else {
                    $output->writeln('<error>There are no running workers.</error>');
                }

                return 1;
            }

            $workers = [$worker];
        }

        foreach ($workers as $worker) {
            $output->writeln(\sprintf('Stopping %s...', $worker->getId()));
            $worker->stop();
        }

        return 0;
    }
}
