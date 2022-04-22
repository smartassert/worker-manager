<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractUnauthorizedUserTest;

class UnauthorizedUserTest extends AbstractUnauthorizedUserTest
{
    use GetApplicationClientTrait;
}
