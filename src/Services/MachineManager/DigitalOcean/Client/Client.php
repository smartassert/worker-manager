<?php

namespace App\Services\MachineManager\DigitalOcean\Client;

use App\Exception\NoDigitalOceanClientException;
use App\Exception\Stack;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\EntityFactory\DropletFactory;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use DigitalOceanV2\Entity\RateLimit;
use DigitalOceanV2\Exception\ApiLimitExceededException;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface as HttpRequestInterface;
use Psr\Http\Message\ResponseInterface;

readonly class Client
{
    private const int DROPLETS_PER_PAGE = 1;
    private const int DROPLET_PAGE = 1;

    /**
     * @var non-empty-string
     */
    private string $baseUrl;

    /**
     * @param non-empty-string   $baseUrl
     * @param non-empty-string[] $tokens
     */
    public function __construct(
        string $baseUrl,
        private array $tokens,
        private ClientInterface $httpClient,
        private DropletFactory $dropletFactory,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    /**
     * @param non-empty-string $name
     *
     * @throws ClientExceptionInterface
     * @throws InvalidEntityDataException
     * @throws NoDigitalOceanClientException
     * @throws EmptyDropletCollectionException
     * @throws ErrorException
     */
    public function getDroplet(string $name): Droplet
    {
        $url = sprintf(
            '/droplets?tag_name=%s&page=%d&per_page=%d',
            $name,
            self::DROPLET_PAGE,
            self::DROPLETS_PER_PAGE,
        );

        $responseData = $this->getResponseData('GET', $url);

        return $this->dropletFactory->createFromSingleCollection($responseData);
    }

    /**
     * @return array<mixed>
     *
     * @throws ClientExceptionInterface
     * @throws NoDigitalOceanClientException
     * @throws ErrorException
     */
    private function getResponseData(string $method, string $url): array
    {
        $responseData = [];

        $response = $this->getResponse($method, $url);
        $statusCode = $response->getStatusCode();

        if ('application/json' === $response->getHeaderLine('Content-Type')) {
            $responseContent = $response->getBody()->getContents();
            $response->getBody()->rewind();

            $responseData = json_decode($responseContent, true);
            $responseData = is_array($responseData) ? $responseData : [];

            if (200 === $statusCode) {
                return $responseData;
            }
        }

        throw $this->createErrorException($responseData, $statusCode);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NoDigitalOceanClientException
     */
    private function getResponse(string $method, string $url): ResponseInterface
    {
        foreach ($this->tokens as $token) {
            $httpRequest = $this->createRequest($token, $method, $url);

            $response = $this->httpClient->sendRequest($httpRequest);
            $statusCode = $response->getStatusCode();

            if (429 === $statusCode) {
                throw $this->createApiLimitExceededException($response);
            }

            if (401 !== $statusCode) {
                return $response;
            }
        }

        // @todo replace in #601
        throw new NoDigitalOceanClientException(new Stack([new RuntimeException('Unauthorized', 401)]));
    }

    /**
     * @param array<mixed> $errorData
     */
    private function createErrorException(array $errorData, int $statusCode): ErrorException
    {
        $errorId = '';
        $errorMessage = '';

        if ([] !== $errorData) {
            $errorId = $errorData['id'] ?? '';
            $errorId = is_string($errorId) ? $errorId : '';

            $errorMessage = $errorData['message'] ?? '';
            $errorMessage = is_string($errorMessage) ? $errorMessage : '';
        }

        return new ErrorException($errorId, $errorMessage, $statusCode);
    }

    private function createApiLimitExceededException(ResponseInterface $response): ApiLimitExceededException
    {
        $responseData = json_decode($response->getBody()->getContents(), true);
        $message = is_array($responseData) ? ($responseData['message'] ?? '') : '';
        $message = is_string($message) ? $message : '';

        throw new ApiLimitExceededException(
            $message,
            429,
            new RateLimit([
                'reset' => (int) $response->getHeaderLine('RateLimit-Reset'),
                'remaining' => (int) $response->getHeaderLine('RateLimit-Remaining'),
                'limit' => (int) $response->getHeaderLine('RateLimit-Limit'),
            ])
        );
    }

    private function createRequest(string $token, string $method, string $url): HttpRequestInterface
    {
        return new Request(
            $method,
            $this->baseUrl . $url,
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ]
        );
    }
}
