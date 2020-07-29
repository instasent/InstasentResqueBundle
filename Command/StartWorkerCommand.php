<?php

declare(strict_types=1);
declare(ticks=1);

namespace Instasent\ResqueBundle\Command;

use Instasent\ResqueBundle\WorkerBase;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

class StartWorkerCommand extends ContainerAwareCommand
{
    /**
     * Command name.
     */
    const NAME = 'instasent:resque:worker-start';

    /**
     * SymfonyStyle|null
     */
    protected $ioStyle;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Start a instasent resque worker')
            ->addOption(
                'logging',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Logging service'
            )
            ->addOption(
                'count',
                'c',
                InputOption::VALUE_REQUIRED,
                'How many workers to fork',
                '1'
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
                WorkerBase::class
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ioStyle = new SymfonyStyle($input, $output);
        $this->ioStyle = $ioStyle;
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
            $this->ioStyle->error($exception->getMessage());

            return 1;
        }

        $commandLine = $this->getCommand($container, $input);

        if (\method_exists(Process::class, 'fromShellCommandline')) {
            $process = Process::fromShellCommandline($commandLine, null, $environment, null, null);
        } else {
            $process = new Process($commandLine, null, $environment, null, null);
        }

        if (!$input->getOption('hide-debug')) {
            $this->ioStyle->debug(\sprintf('Starting worker %s', $commandLine));
        }

        if (!$input->getOption('foreground')) {
            if (!$input->getOption('hide-debug')) {
                $this->ioStyle->debug(\sprintf(
                    'Starting worker %s:%s:%s',
                    \function_exists('gethostname') ? \gethostname() : \php_uname('n'),
                    \trim($process->getOutput()),
                    $input->getArgument('queues')
                ));
            }

            $process->run();

            return 0;
        }

        $this->registerSignalHandlers($process);

        $process->run(function ($type, $buffer) use ($ioStyle) {
            $ioStyle->text($buffer);
        });

        return 0;
    }

    /**
     * Prepare signaling.
     *
     * @param SymfonyStyle $this->ioStyle
     * @param Process      $process
     */
    final protected function registerSignalHandlers(Process $process)
    {
        $ioStyle = $this->ioStyle;

        $closeHandler = function ($signal) use ($ioStyle, $process) {
            $environment = $process->getEnv();
            $pid = $process->getPid();

            $pidFile = $environment['PIDFILE'];
            if (!$pid || !\file_exists($pidFile)) {
                $ioStyle->error(\sprintf('pid not provided by process and PID file %s does not exist', $pidFile));

                return;
            }

            $process->signal($signal);

            if(!$process->getPid()) {
                $pid = \file_get_contents($pidFile);
            }

            \posix_kill($pid, $signal);
            \unlink($pidFile);
        };

        \pcntl_signal(\SIGTERM, $closeHandler);
        \pcntl_signal(\SIGINT, $closeHandler);
        \pcntl_signal(\SIGQUIT, $closeHandler);
    }

    /**
     * Get environment data.
     *
     * @param ContainerInterface $container
     * @param InputInterface     $input
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getEnvironment(ContainerInterface $container, InputInterface $input): array
    {
        $environment = $this->getRootEnvironment($container, $input);
        $environment = $this->getResqueEnvironment($environment, $container, $input);
        $environment = $this->getWorkerEnvironment($environment, $input);

        $count = (int) $input->getOption('count');
        if ($count < 1) {
            throw new \Exception('Workers count must be higher than 0');
        }
        $environment['COUNT'] = $count;

        return $environment;
    }

    /**
     * Get basic environment data.
     *
     * @param ContainerInterface $container
     * @param InputInterface     $input
     *
     * @return array
     */
    final protected function getRootEnvironment(ContainerInterface $container, InputInterface $input): array
    {
        if (\version_compare(\PHP_VERSION, '5.5.0') >= 0) {
            // here to work around issues with pcntl and cli_set_process_title in PHP > 5.5
            $environment = $_SERVER;

            unset(
                $environment['_'],
                $environment['PHP_SELF'],
                $environment['SCRIPT_NAME'],
                $environment['SCRIPT_FILENAME'],
                $environment['PATH_TRANSLATED'],
                $environment['argv']
            );
        } else {
            $environment = [];
        }

        $pidFile = $this->getContainer()->get('kernel')->getCacheDir().'/'.\uniqid('resque-worker-', true).'.pid';
        $environment['PIDFILE'] = $pidFile;

        if (!$input->getOption('hide-debug')) {
            $environment['VERBOSE'] = 1;
        }

        if ($input->getOption('verbose')) {
            $environment['VVERBOSE'] = 1;
        }

        $environment['SYMFONY_ENV'] = \getenv('APP_ENV') !== false
            ? \getenv('APP_ENV')
            : $container->getParameter('kernel.environment');

        $rootDir = $container->getParameter('kernel.root_dir');

        $cacheFiles = [
            $rootDir.'/../var/bootstrap.php.cache',
            $rootDir.'/bootstrap.php.cache',
        ];
        foreach ($cacheFiles as $kernelFile) {
            if (\file_exists($kernelFile)) {
                $environment['APP_INCLUDE'] = $kernelFile;
                break;
            }
        }

        $kernelFiles = [
            $rootDir.'/Kernel.php',
            $rootDir.'/../app/AppKernel.php',
        ];
        foreach ($kernelFiles as $kernelFile) {
            if (\file_exists($kernelFile)) {
                $environment['APP_KERNEL'] = $kernelFile;
                break;
            }
        }

        return $environment;
    }

    /**
     * Get resque environment data.
     *
     * @param array              $environment
     * @param ContainerInterface $container
     * @param InputInterface     $input
     *
     * @return array
     */
    protected function getResqueEnvironment(
        array $environment,
        ContainerInterface $container,
        InputInterface $input
    ): array {
        $interval = $input->getOption('interval');
        if ($interval < 1) {
            throw new \Exception('Workers interval must be higher than 0');
        }
        $environment['INTERVAL'] = $interval;

        $prefix = $container->getParameter('instasent_resque.prefix');
        if (!empty($prefix)) {
            $environment['PREFIX'] = $prefix;
        }

        $redisHost = $container->getParameter('instasent_resque.resque.redis.host');
        $redisPort = $container->getParameter('instasent_resque.resque.redis.port');
        $redisDsn = $container->getParameter('instasent_resque.resque.redis.dsn');

        if (!empty($redisHost) && !empty($redisPort)) {
            $environment['REDIS_BACKEND'] = $redisHost.':'.$redisPort;

            $redisDatabase = $container->getParameter('instasent_resque.resque.redis.database');
            if (!empty($redisDatabase)) {
                $environment['REDIS_BACKEND_DB'] = $redisDatabase;
            }
        }

        if ($redisDsn) {
            $environment['REDIS_BACKEND'] = $redisDsn;
        }

        $logger = $input->getOption('logging');
        $environment['LOG_CHANNEL'] = $logger;

        if ($logger && !$container->has($logger)) {
            $this->ioStyle->info('Logger is not defined or channel is not present in container');
        }

        return $environment;
    }

    /**
     * Get worker environment data.
     *
     * @param array          $environment
     * @param InputInterface $input
     *
     * @return array
     */
    protected function getWorkerEnvironment(array $environment, InputInterface $input)
    {
        if ($input->getOption('blocking') !== null) {
            $environment['BLOCKING'] = 1;
        }

        $environment['WORKER_CLASS'] = $input->getOption('worker');
        $environment['QUEUE'] = $input->getArgument('queues');

        return $environment;
    }

    /**
     * Get executing command.
     *
     * @param ContainerInterface $container
     * @param InputInterface     $input
     *
     * @return string
     */
    final protected function getCommand(ContainerInterface $container, InputInterface $input)
    {
        $php = \PHP_BINARY;
        if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
            $php = 'php';
        }

        $options = [];

        $memoryLimit = (int) $input->getOption('memory-limit');
        if ($memoryLimit !== 0) {
            $options[] = \sprintf('-d memory_limit=%dM', $memoryLimit);
        }

        $binaryName = $this->getBinaryName();
        $command = \sprintf(
            '%s %s %s',
            $php,
            \implode(' ', $options),
            __DIR__.'/../bin/'.$binaryName
        );

        if (!$input->getOption('foreground')) {
            $command = \sprintf(
                'nohup %s > %s/%s.log 2>&1 & echo $!',
                $command,
                $container->get('kernel.logs_dir'),
                $binaryName
            );
        }

        return $command;
    }

    /**
     * Get command binary name.
     *
     * @return string
     */
    protected function getBinaryName(): string
    {
        return 'resque';
    }
}
