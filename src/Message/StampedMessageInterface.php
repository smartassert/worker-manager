<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Stamp\StampInterface;

interface StampedMessageInterface
{
    /**
     * @return StampInterface[]
     */
    public function getStamps(): array;

    public function clearStamps(): void;
}
