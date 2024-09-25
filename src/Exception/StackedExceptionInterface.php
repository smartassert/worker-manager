<?php

namespace App\Exception;

interface StackedExceptionInterface
{
    /**
     * @return non-empty-array<\Throwable>
     */
    public function getExceptionStack(): array;
}
