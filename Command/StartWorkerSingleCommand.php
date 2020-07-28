<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\Command;

use Instasent\ResqueBundle\WorkerSingle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StartWorkerSingleCommand extends StartWorkerCommand
{
    /**
     * Command name.
     */
    const NAME = 'instasent:resque:worker-single-start';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Start a instasent resque worker single')
            ->addOption(
                'logging',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Logging service'
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_REQUIRED,
                'How often to check for new jobs across the queues',
                (string) \Resque::DEFAULT_INTERVAL
            )
            ->addOption(
                'worker',
                'w',
                InputOption::VALUE_OPTIONAL,
                'Worker class',
                WorkerSingle::class
            )
            ->addOption(
                'blocking',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Worker blocking'
            )
            ->addOption(
                'foreground',
                'f',
                InputOption::VALUE_NONE,
                'Should the worker run in foreground'
            )
            ->addOption(
                'hide-debug',
                null,
                InputOption::VALUE_NONE,
                'Do not show debug information'
            )
            ->addOption(
                'memory-limit',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Force cli memory_limit (expressed in Mbytes)',
                '0'
            )
            ->addArgument(
                'queues',
                InputArgument::REQUIRED,
                'Queue names (separate using comma)'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function getEnvironment(ContainerInterface $container, InputInterface $input): array
    {
        $environment = $this->getRootEnvironment($container, $input);
        $environment = $this->getResqueEnvironment($environment, $container, $input);
        $environment = $this->getWorkerEnvironment($environment, $input);

        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryName(): string
    {
        return 'resque-single';
    }
}
