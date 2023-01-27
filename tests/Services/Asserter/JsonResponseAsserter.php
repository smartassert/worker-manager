<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class JsonResponseAsserter
{
    /**
     * @param array<mixed> $expectedResponseData
     * @param string[]     $excludedKeys
     */
    public function assertJsonResponse(
        ResponseInterface $response,
        int $expectedStatusCode,
        array $expectedResponseData,
        array $excludedKeys = [],
    ): void {
        Assert::assertSame($expectedStatusCode, $response->getStatusCode());
        Assert::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        Assert::assertIsArray($responseData);

        foreach ($excludedKeys as $key) {
            unset($responseData[$key], $expectedResponseData[$key]);
        }

        Assert::assertEquals($expectedResponseData, $responseData);
    }
}
