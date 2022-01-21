<?php

namespace App\Exception;

interface StackedExceptionInterface
{
    /**
     * @return \Throwable[]
     */
    public function getExceptionStack(): array;
}
