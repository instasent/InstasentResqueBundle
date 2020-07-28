<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle;

interface JobInterface
{
    public function getName();

    public function getQueue();

    public function getArguments();

    public function hasArgument($arg);

    public function getArgument($arg);

    public function setArgument($arg, $value);

    public function perform();

    public function setUp();

    public function tearDown();
}
