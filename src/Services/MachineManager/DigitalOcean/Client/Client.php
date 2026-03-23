<?php

namespace App\Services\MachineManager\DigitalOcean\Client;

use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\Entity\Error;
use App\Services\MachineManager\DigitalOcean\EntityFactory\DropletFactory;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\DropletLimitReachedException;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\DigitalOcean\Exception\MissingDropletException;
use App\Services\MachineManager\DigitalOcean\Request\CreateDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\GetDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\RemoveDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\RequestInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface as HttpRequestInterface;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;

readonly class Client
{
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
     * @throws AuthenticationException
     * @throws EmptyDropletCollectionException
     * @throws ErrorException
     * @throws ApiLimitExceededException
     */
    public function getDroplet(string $name): Droplet
    {
        $dropletCollectionData = $this->getResponseData(new GetDropletRequest($name));

        return $this->dropletFactory->createSingleFromCollection($dropletCollectionData);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws ErrorException
     * @throws AuthenticationException
     * @throws MissingDropletException
     * @throws ApiLimitExceededException
     */
    public function deleteDroplet(string $name): void
    {
        $request = new RemoveDropletRequest($name);
        $response = $this->getResponse($request);
        $statusCode = $response->getStatusCode();

        if (204 === $statusCode) {
            return;
        }

        if (404 === $statusCode) {
            throw new MissingDropletException();
        }

        throw $this->createErrorException(
            $statusCode,
            $this->getRawResponseData($response),
            $request,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws ErrorException
     * @throws InvalidEntityDataException
     * @throws AuthenticationException
     * @throws ApiLimitExceededException
     */
    public function createDroplet(Configuration $configuration): Droplet
    {
        $dropletData = $this->getResponseData(
            new CreateDropletRequest($configuration)
        );

        return $this->dropletFactory->create($dropletData);
    }

    /**
     * @return array<mixed>
     *
     * @throws ClientExceptionInterface
     * @throws AuthenticationException
     * @throws ErrorException
     * @throws ApiLimitExceededException
     */
    private function getResponseData(RequestInterface $request): array
    {
        $response = $this->getResponse($request);
        $statusCode = $response->getStatusCode();
        $responseData = $this->getRawResponseData($response);

        if (
            (200 === $statusCode || 202 == $statusCode)
            && str_starts_with($response->getHeaderLine('Content-Type'), 'application/json')
        ) {
            return $responseData;
        }

        throw $this->createErrorException($statusCode, $responseData, $request);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws AuthenticationException
     * @throws ApiLimitExceededException
     * @throws ErrorException
     */
    private function getResponse(RequestInterface $request): ResponseInterface
    {
        foreach ($this->tokens as $token) {
            $httpRequest = $this->createRequest($token, $request);

            $response = $this->httpClient->sendRequest($httpRequest);
            $statusCode = $response->getStatusCode();

            if (429 === $statusCode) {
                throw $this->createApiLimitExceededException($response);
            }

            if (422 === $statusCode) {
                throw $this->createErrorException(
                    422,
                    $this->getRawResponseData($response),
                    $request,
                );
            }

            if (401 !== $statusCode) {
                return $response;
            }
        }

        throw new AuthenticationException();
    }

    /**
     * @param array<mixed> $responseData
     */
    private function createErrorException(
        int $statusCode,
        array $responseData,
        RequestInterface $request
    ): ErrorException {
        $error = $this->createErrorEntity($statusCode, $responseData);

        if (str_contains($error->message, DropletLimitReachedException::MESSAGE_IDENTIFIER)) {
            return new DropletLimitReachedException($error, $request);
        }

        return new ErrorException($error, $request);
    }

    private function createApiLimitExceededException(ResponseInterface $response): ApiLimitExceededException
    {
        $responseData = $this->getRawResponseData($response);
        $error = $this->createErrorEntity($response->getStatusCode(), $responseData);

        return new ApiLimitExceededException(
            $error,
            (int) $response->getHeaderLine('RateLimit-Reset'),
            (int) $response->getHeaderLine('RateLimit-Remaining'),
            (int) $response->getHeaderLine('RateLimit-Limit'),
        );
    }

    /**
     * @param array<mixed> $responseData
     */
    private function createErrorEntity(int $code, array $responseData): Error
    {
        $id = $responseData['id'] ?? '';
        $id = is_string($id) ? $id : '';

        $message = $responseData['message'] ?? '';
        $message = is_string($message) ? $message : '';

        return new Error($code, $id, $message);
    }

    private function createRequest(string $token, RequestInterface $request): HttpRequestInterface
    {
        $body = null;
        if (is_array($request->getPayload())) {
            $body = (string) json_encode($request->getPayload());
        }

        return new Request(
            $request->getMethod(),
            $this->baseUrl . ltrim($request->getUrl(), '/'),
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            $body
        );
    }

    /**
     * @return array<mixed>
     */
    private function getRawResponseData(ResponseInterface $response): array
    {
        $responseData = [];

        if (str_starts_with($response->getHeaderLine('Content-Type'), 'application/json')) {
            $responseContent = $response->getBody()->getContents();
            $response->getBody()->rewind();

            $responseData = json_decode($responseContent, true);
            $responseData = is_array($responseData) ? $responseData : [];
        }

        return $responseData;
    }
}
