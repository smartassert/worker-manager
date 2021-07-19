<?php

namespace App\Services;

interface RequestIdFactoryInterface
{
    public function create(): string;
}
