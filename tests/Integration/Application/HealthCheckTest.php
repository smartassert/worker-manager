<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Tests\Application\AbstractHealthCheckTestCase;
use App\Tests\Integration\GetApplicationClientTrait;

class HealthCheckTest extends AbstractHealthCheckTestCase
{
    use GetApplicationClientTrait;

    protected function getHealthCheckSetup(): void {}
}
