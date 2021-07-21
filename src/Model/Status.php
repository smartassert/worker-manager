<?php

namespace App\Model;

class Status implements \JsonSerializable
{
    public function __construct(
        private string $version,
        private int $messageQueueSize,
    ) {
    }

    /**
     * @return array{'version': string, 'message-queue-size': int}
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'message-queue-size' => $this->messageQueueSize,
        ];
    }
}
