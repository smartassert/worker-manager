<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Tests\Application\AbstractHealthCheckTest;
use App\Tests\Integration\GetApplicationClientTrait;

class HealthCheckTest extends AbstractHealthCheckTest
{
    use GetApplicationClientTrait;

    protected function getHealthCheckSetup(): void
    {
    }
}
