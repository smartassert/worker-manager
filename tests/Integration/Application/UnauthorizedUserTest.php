<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Tests\Application\AbstractUnauthorizedUserTest;
use App\Tests\Integration\GetApplicationClientTrait;

class UnauthorizedUserTest extends AbstractUnauthorizedUserTest
{
    use GetApplicationClientTrait;
}
