<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

interface ContainerAwareJobInterface extends JobInterface
{
    /**
     * @param mixed[] $kernelOptions
     */
    public function setKernelOptions(array $kernelOptions);
}
