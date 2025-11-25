<?php

namespace App\Services;

use Symfony\Component\Uid\Ulid;

class RequestIdFactory implements RequestIdFactoryInterface
{
    public function create(): string
    {
        return (string) new Ulid();
    }
}
