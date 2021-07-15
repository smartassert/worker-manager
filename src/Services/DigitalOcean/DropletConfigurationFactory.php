<?php

namespace App\Services\DigitalOcean;

use App\Model\DigitalOcean\DropletConfiguration;

class DropletConfigurationFactory
{
    public const KEY_NAMES = 'names';
    public const KEY_REGION = 'region';
    public const KEY_SIZE = 'size';
    public const KEY_IMAGE = 'image';
    public const KEY_BACKUPS = 'backups';
    public const KEY_IPV6 = 'ipv6';
    public const KEY_VPC_UUID = 'vpc-uuid';
    public const KEY_SSH_KEYS = 'ssk-keys';
    public const KEY_USER_DATA = 'user-data';
    public const KEY_MONITORING = 'monitoring';
    public const KEY_VOLUMES = 'volumes';
    public const KEY_TAGS = 'tags';

    /**
     * @param array<mixed> $defaults
     */
    public function __construct(
        private array $defaults = []
    ) {
    }

    public function create(): DropletConfiguration
    {
        return (new DropletConfiguration())
            ->withNames($this->getStringValues(self::KEY_NAMES))
            ->withRegion($this->getStringValue(self::KEY_REGION))
            ->withSize($this->getStringValue(self::KEY_SIZE))
            ->withImage($this->getStringValue(self::KEY_IMAGE))
            ->withBackups($this->getBooleanValue(self::KEY_BACKUPS))
            ->withIpv6($this->getBooleanValue(self::KEY_IPV6))
            ->withVpcUuid($this->getVpcUuidValue())
            ->withSshKeys($this->getIntValues(self::KEY_SSH_KEYS))
            ->withUserData($this->getStringValue(self::KEY_USER_DATA))
            ->withMonitoring($this->getBooleanValue(self::KEY_MONITORING))
            ->withVolumes($this->getStringValues(self::KEY_VOLUMES))
            ->withTags($this->getStringValues(self::KEY_TAGS))
        ;
    }

    private function getVpcUuidValue(): string | bool
    {
        $value = $this->defaults[self::KEY_VPC_UUID] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value;
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getStringValues(string $key): array
    {
        $values = $this->getValues($key);

        return array_filter($values, function ($item) {
            return is_string($item);
        });
    }

    /**
     * @return int[]
     */
    private function getIntValues(string $key): array
    {
        $values = $this->getValues($key);

        return array_filter($values, function ($item) {
            return is_int($item);
        });
    }

    /**
     * @return array<mixed>
     */
    private function getValues(string $key): array
    {
        $values = $this->defaults[$key] ?? [];

        return is_array($values) ? $values : [];
    }

    private function getStringValue(string $key): string
    {
        $value = $this->defaults[$key] ?? '';

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function getBooleanValue(string $key, bool $default = false): bool
    {
        $value = $this->defaults[$key] ?? '';

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (bool) $value;
        }

        return $default;
    }
}
