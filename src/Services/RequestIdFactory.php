<?php

namespace App\Services;

use Symfony\Component\Uid\Ulid;

class RequestIdFactory implements RequestIdFactoryInterface
{
    public function create(): string
    {
        $id = (string) new Ulid();
        if ('' === $id) {
            throw new \RuntimeException('Generated id is empty');
        }

        return $id;
    }
}
