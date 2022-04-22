<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractStatusTest;

class StatusTest extends AbstractStatusTest
{
    use GetApplicationClientTrait;

    protected function getExpectedReadyValue(): bool
    {
        return false;
    }

    protected function getExpectedVersion(): string
    {
        return 'docker_compose_version';
    }
}
