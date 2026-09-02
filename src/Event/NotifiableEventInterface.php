<?php

declare(strict_types=1);

namespace App\Event;

interface NotifiableEventInterface
{
    public function getNotifyUrl(): ?string;

    /**
     * @return non-empty-string
     */
    public function getRemoteEventName(): string;

    /**
     * @return array<mixed>
     */
    public function getPayload(): array;
}
