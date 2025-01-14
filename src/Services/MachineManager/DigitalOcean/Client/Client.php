<?php

namespace App\Services\MachineManager\DigitalOcean\Client;

use App\Exception\NoDigitalOceanClientException;
use App\Exception\Stack;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;
use App\Services\MachineManager\DigitalOcean\EntityFactory\DropletFactory;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use App\Services\MachineManager\DigitalOcean\Exception\MissingDropletException;
use App\Services\MachineManager\DigitalOcean\Request\CreateDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\GetDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\RemoveDropletRequest;
use App\Services\MachineManager\DigitalOcean\Request\RequestInterface;
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
        $responseData = $this->getResponseData(new GetDropletRequest($name));

        return $this->dropletFactory->createFromSingleCollection($responseData);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws ErrorException
     * @throws NoDigitalOceanClientException
     * @throws MissingDropletException
     */
    public function deleteDroplet(string $name): void
    {
        $response = $this->getResponse(new RemoveDropletRequest($name));
        $statusCode = $response->getStatusCode();

        if (204 === $statusCode) {
            return;
        }

        if (404 === $statusCode) {
            throw new MissingDropletException();
        }

        throw $this->createErrorException($response);
    }

    /**
     * @param string[] $tags
     *
     * @throws ClientExceptionInterface
     * @throws EmptyDropletCollectionException
     * @throws ErrorException
     * @throws InvalidEntityDataException
     * @throws NoDigitalOceanClientException
     */
    public function createDroplet(
        string $name,
        string $region,
        string $size,
        string $image,
        array $tags,
        string $userData
    ): Droplet {
        $responseData = $this->getResponseData(
            new CreateDropletRequest($name, $region, $size, $image, $tags, $userData)
        );

        return $this->dropletFactory->createFromSingleCollection($responseData);
    }

    /**
     * @return array<mixed>
     *
     * @throws ClientExceptionInterface
     * @throws NoDigitalOceanClientException
     * @throws ErrorException
     */
    private function getResponseData(RequestInterface $request): array
    {
        $response = $this->getResponse($request);

        if (
            in_array($response->getStatusCode(), [200, 202])
            && 'application/json' === $response->getHeaderLine('Content-Type')
        ) {
            $responseContent = $response->getBody()->getContents();
            $response->getBody()->rewind();

            $responseData = json_decode($responseContent, true);

            return is_array($responseData) ? $responseData : [];
        }

        throw $this->createErrorException($response);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NoDigitalOceanClientException
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

            if (401 !== $statusCode) {
                return $response;
            }
        }

        // @todo replace in #601
        throw new NoDigitalOceanClientException(new Stack([new RuntimeException('Unauthorized', 401)]));
    }

    private function createErrorException(ResponseInterface $response): ErrorException
    {
        $errorData = [];
        if ('application/json' === $response->getHeaderLine('Content-Type')) {
            $errorData = json_decode($response->getBody()->getContents(), true);
            $errorData = is_array($errorData) ? $errorData : [];
        }

        $errorId = $errorData['id'] ?? '';
        $errorId = is_string($errorId) ? $errorId : '';

        $errorMessage = $errorData['message'] ?? '';
        $errorMessage = is_string($errorMessage) ? $errorMessage : '';

        return new ErrorException($errorId, $errorMessage, $response->getStatusCode());
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

    private function createRequest(string $token, RequestInterface $request): HttpRequestInterface
    {
        $body = null;
        if (is_array($request->getPayload())) {
            $body = (string) json_encode($request->getPayload());
        }

        return new Request(
            $request->getMethod(),
            $this->baseUrl . $request->getUrl(),
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            $body
        );
    }
}
