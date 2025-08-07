<?php

namespace NinjaCharts\Framework\Container\Contracts;

use Exception;
use NinjaCharts\Framework\Container\Contracts\Psr\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
