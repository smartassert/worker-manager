<?php

namespace App\Tests\Services;

use App\Services\RequestIdFactoryInterface;

class SequentialRequestIdFactory implements RequestIdFactoryInterface
{
    private int $sequenceCounter = 0;

    public function create(): string
    {
        $id = 'id' . $this->sequenceCounter;
        ++$this->sequenceCounter;

        return $id;
    }

    public function reset(int $sequenceCounter = 0): void
    {
        $this->sequenceCounter = $sequenceCounter;
    }
}
