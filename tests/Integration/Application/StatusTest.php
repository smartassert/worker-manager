<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Tests\Application\AbstractStatusTestCase;
use App\Tests\Integration\GetApplicationClientTrait;

class StatusTest extends AbstractStatusTestCase
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
