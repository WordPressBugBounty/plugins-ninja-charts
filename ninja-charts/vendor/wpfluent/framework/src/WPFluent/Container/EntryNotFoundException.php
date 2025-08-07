<?php

namespace NinjaCharts\Framework\Container;

use Exception;
use NinjaCharts\Framework\Container\Contracts\Psr\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
