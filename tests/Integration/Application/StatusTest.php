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
        $defaultVersion = 'docker_compose_version';
        $version = $_SERVER['EXPECTED_VERSION'] ?? $defaultVersion;

        return is_string($version) ? $version : $defaultVersion;
    }
}
