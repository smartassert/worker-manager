<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\MessageHandlingReadiness;
use Symfony\Contracts\EventDispatcher\Event;

class MessageNotHandleableEvent extends Event
{
    public function __construct(
        public readonly object $message,
        public readonly MessageHandlingReadiness $readiness,
    ) {}

    public function getMessage(): object
    {
        return $this->message;
    }
}
