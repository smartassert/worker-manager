<?php

namespace App\Services;

interface RequestIdFactoryInterface
{
    /**
     * @return non-empty-string
     */
    public function create(): string;
}
