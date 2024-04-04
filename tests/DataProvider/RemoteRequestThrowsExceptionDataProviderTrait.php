<?php

declare(strict_types=1);

namespace App\Tests\DataProvider;

use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

trait RemoteRequestThrowsExceptionDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function remoteRequestThrowsExceptionDataProvider(): array
    {
        return [
            VendorApiLimitExceededException::class => [
                'dropletApiException' => new VendorApiLimitExceededException('Too Many Requests', 429),
                'expectedExceptionClass' => ApiLimitExceededException::class,
            ],
            RuntimeException::class . ' HTTP 503' => [
                'dropletApiException' => new RuntimeException('Service Unavailable', 503),
                'expectedExceptionClass' => HttpException::class,
            ],
            ValidationFailedException::class => [
                'dropletApiException' => new ValidationFailedException('Bad Request', 400),
                'expectedExceptionClass' => Exception::class,
            ],
        ];
    }
}
