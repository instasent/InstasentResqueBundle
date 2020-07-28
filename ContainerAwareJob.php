<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class ContainerAwareJob extends Job implements ContainerAwareJobInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @return ContainerInterface
     */
    protected function getContainer(): ContainerInterface
    {
        if (isset($GLOBALS['KERNEL']) && $GLOBALS['KERNEL'] !== null) {
            $this->kernel = $GLOBALS['KERNEL'];
        }

        if ($this->kernel === null) {
            $this->kernel = $this->createKernel();
            $this->kernel->boot();
        }

        return $this->kernel->getContainer();
    }

    /**
     * {@inheritdoc}
     */
    public function setKernelOptions(array $kernelOptions)
    {
        $this->args = \array_merge($this->args, $kernelOptions);
    }

    /**
     * @return KernelInterface
     */
    protected function createKernel(): KernelInterface
    {
        $finder = new Finder();
        $finder->name('*Kernel.php')->depth(0)->in($this->args['kernel.root_dir']);
        $results = \iterator_to_array($finder);
        $file = \current($results);
        $class = $file->getBasename('.php');

        require_once $file;

        return new $class(
            $this->args['kernel.environment'] ?? 'dev',
            $this->args['kernel.debug'] ?? true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        if (isset($GLOBALS['KERNEL']) && $GLOBALS['KERNEL'] !== null) {
            return;
        }

        if ($this->kernel) {
            $this->kernel->shutdown();
        }
    }
}
