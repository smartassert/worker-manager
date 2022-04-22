<?php

declare(strict_types=1);

namespace App\Tests\Services;

class AuthenticationConfiguration
{
    public function __construct(
        public readonly string $validToken,
        public readonly string $invalidToken,
        public readonly string $headerName,
        public readonly string $headerValuePrefix,
        public readonly string $authenticatedUserId,
    ) {
    }
}
