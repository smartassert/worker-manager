<?php

declare(strict_types=1);

namespace App\Tests\Services;

use SmartAssert\UsersClient\Client;
use SmartAssert\UsersClient\Model\ApiKey;
use SmartAssert\UsersClient\Model\RefreshableToken;
use SmartAssert\UsersClient\Model\Token;
use SmartAssert\UsersClient\Model\User;

class AuthenticationConfiguration
{
    private RefreshableToken $frontendToken;
    private ApiKey $apiKey;
    private Token $apiToken;
    private User $user;

    public function __construct(
        public readonly string $userEmail,
        public readonly string $userPassword,
        private readonly Client $usersClient,
    ) {
    }

    public function getValidApiToken(): string
    {
        if (!isset($this->apiToken)) {
            $this->apiToken = $this->usersClient->createApiToken($this->getDefaultApiKey()->key);
        }

        return $this->apiToken->token;
    }

    public function getInvalidApiToken(): string
    {
        return 'invalid api token value';
    }

    public function getUser(): User
    {
        if (!isset($this->user)) {
            $user = $this->usersClient->verifyFrontendToken($this->getFrontendToken()->token);
            if (null === $user) {
                throw new \RuntimeException('User is null');
            }

            $this->user = $user;
        }

        return $this->user;
    }

    private function getFrontendToken(): RefreshableToken
    {
        if (!isset($this->frontendToken)) {
            $this->frontendToken = $this->usersClient->createFrontendToken($this->userEmail, $this->userPassword);
        }

        return $this->frontendToken;
    }

    private function getDefaultApiKey(): ApiKey
    {
        if (!isset($this->apiKey)) {
            $apiKeys = $this->usersClient->listUserApiKeys($this->getFrontendToken()->token);
            $apiKey = $apiKeys->getDefault();
            if (null === $apiKey) {
                throw new \RuntimeException('API key is null');
            }

            $this->apiKey = $apiKey;
        }

        return $this->apiKey;
    }
}
