<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearQueueCommand extends ContainerAwareCommand
{
    /**
     * Command name.
     */
    const NAME = 'instasent:resque:clear-queue';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Clear a Instasent queue')
            ->addArgument(
                'queue',
                InputArgument::REQUIRED,
                'Queue names (separate using comma)'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Instasent\ResqueBundle\Resque $resque */
        $resque = $this->getContainer()->get('instasent_resque.resque');

        $queues = \explode(',', $input->getArgument('queue'));
        foreach ($queues as $queue) {
            $count = $resque->clearQueue($queue);

            $output->writeln('Cleared queue '.$queue.' - removed '.$count.' entries');
        }

        return 0;
    }
}
