<?php

namespace App\Request;

class CreateMachineRequest
{
    public const string KEY_ID = 'id';

    /**
     * @param ?non-empty-string $id
     */
    public function __construct(
        public readonly ?string $id,
    ) {}
}
