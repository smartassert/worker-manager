<?php

namespace App\Request;

class CreateMachineRequest
{
    public const string KEY_ID = 'id';
    public const string KEY_NOTIFY_URL = 'notify_url';

    /**
     * @param non-empty-string  $id
     * @param ?non-empty-string $notifyUrl
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $notifyUrl,
    ) {}
}
