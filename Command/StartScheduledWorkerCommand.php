<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

class StartScheduledWorkerCommand extends StartWorkerCommand
{
    /**
     * Command name.
     */
    const NAME = 'instasent:resque:scheduledworker-start';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Start a instasent scheduled resque worker')
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
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force creation of a new worker if the PID file exists'
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
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ioStyle = new SymfonyStyle($input, $output);

        $container = $this->getContainer();

        try {
            $environment = $this->getEnvironment($container, $input);

            // In windows: When you pass an environment to CMD it replaces the old environment
            // That means we create a lot of problems with respect to user accounts and missing vars
            // this is a workaround where we add the vars to the existing environment.
            if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
                foreach ($environment as $var => $value) {
                    \putenv($var.'='.$value);
                }

                $environment = null;
            }
        } catch (\Exception $exception) {
            $ioStyle->error($exception->getMessage());

            return 1;
        }

        $commandLine = $this->getCommand($container, $input);

        if (\method_exists(Process::class, 'fromShellCommandline')) {
            $process = Process::fromShellCommandline($commandLine, null, $environment, null, null);
        } else {
            $process = new Process($commandLine, null, $environment, null, null);
        }

        if (!$input->getOption('hide-debug')) {
            $ioStyle->note(\sprintf('Starting worker %s', $commandLine));
        }

        if (!$input->getOption('foreground')) {
            $pidFile = $container->get('kernel')->getCacheDir().'/instasent_resque_scheduledworker.pid';
            if (\file_exists($pidFile) && !$input->getOption('force')) {
                $ioStyle->error('PID file exists - use --force to override');

                return 1;
            }

            if (\file_exists($pidFile)) {
                \unlink($pidFile);
            }

            $process->run();

            $pid = \trim($process->getOutput());
            \file_put_contents($pidFile, $pid);

            if (!$input->getOption('hide-debug')) {
                $ioStyle->text(\sprintf(
                    'Starting worker %s:%s:%s',
                    \function_exists('gethostname') ? \gethostname() : \php_uname('n'),
                    $pid,
                    $input->getArgument('queues')
                ));
            }

            return 0;
        }

        $this->registerSignalHandlers($ioStyle, $process);

        $process->run(function ($type, $buffer) use ($ioStyle) {
            $ioStyle->text($buffer);
        });

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEnvironment(ContainerInterface $container, InputInterface $input): array
    {
        $environment = $this->getRootEnvironment($container, $input);
        $environment = $this->getResqueEnvironment($environment, $container, $input);

        $vendorDir = $container->getParameter('instasent_resque.resque.vendor_dir');
        $environment['RESQUE_PHP'] = $vendorDir.'/chrisboulton/php-resque/lib/Resque.php';

        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryName(): string
    {
        return 'resque-scheduler';
    }
}
