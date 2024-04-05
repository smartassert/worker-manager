<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Tests\Application\AbstractStatusTest;
use App\Tests\Integration\GetApplicationClientTrait;

class StatusTest extends AbstractStatusTest
{
    use GetApplicationClientTrait;

    protected function getExpectedReadyValue(): bool
    {
        return false;
    }

    protected function getExpectedVersion(): string
    {
        return $_SERVER['EXPECTED_VERSION'] ?? 'docker_compose_version';
    }
}
