<?php

namespace App\Model\DigitalOcean;

class DropletConfiguration
{
    /**
     * @var string[]
     */
    private array $names = [];

    private string $region = '';
    private string $size = '';
    private string $image = '';
    private bool $backups = false;
    private bool $ipv6 = false;
    private string | bool $vpcUuid = false;

    /**
     * @var int[]
     */
    private array $sshKeys = [];

    private string $userData = '';
    private bool $monitoring = true;

    /**
     * @var string[]
     */
    private array $volumes = [];

    /**
     * @var string[]
     */
    private array $tags = [];

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return $this->names;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getBackups(): bool
    {
        return $this->backups;
    }

    public function getIpv6(): bool
    {
        return $this->ipv6;
    }

    public function getVpcUuid(): string | bool
    {
        return $this->vpcUuid;
    }

    /**
     * @return int[]
     */
    public function getSshKeys(): array
    {
        return $this->sshKeys;
    }

    public function getUserData(): string
    {
        return $this->userData;
    }

    public function getMonitoring(): bool
    {
        return $this->monitoring;
    }

    /**
     * @return string[]
     */
    public function getVolumes(): array
    {
        return $this->volumes;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $names
     */
    public function withNames(array $names): self
    {
        $new = clone $this;
        $new->names = array_filter($names, function ($item) {
            return is_string($item);
        });

        return $new;
    }

    public function withRegion(string $region): self
    {
        $new = clone $this;
        $new->region = $region;

        return $new;
    }

    public function withSize(string $size): self
    {
        $new = clone $this;
        $new->size = $size;

        return $new;
    }

    public function withImage(string $image): self
    {
        $new = clone $this;
        $new->image = $image;

        return $new;
    }

    public function withBackups(bool $backups): self
    {
        $new = clone $this;
        $new->backups = $backups;

        return $new;
    }

    public function withIpv6(bool $ipv6): self
    {
        $new = clone $this;
        $new->ipv6 = $ipv6;

        return $new;
    }

    public function withVpcUuid(string | bool $vpcUuid): self
    {
        $new = clone $this;
        $new->vpcUuid = $vpcUuid;

        return $new;
    }

    /**
     * @param int[] $sshKeys
     */
    public function withSshKeys(array $sshKeys): self
    {
        $new = clone $this;
        $new->sshKeys = array_filter($sshKeys, function ($item) {
            return is_int($item);
        });

        return $new;
    }

    public function withUserData(string $userData): self
    {
        $new = clone $this;
        $new->userData = $userData;

        return $new;
    }

    public function withMonitoring(bool $monitoring): self
    {
        $new = clone $this;
        $new->monitoring = $monitoring;

        return $new;
    }

    /**
     * @param string[] $volumes
     */
    public function withVolumes(array $volumes): self
    {
        $new = clone $this;
        $new->volumes = array_filter($volumes, function ($item) {
            return is_string($item);
        });

        return $new;
    }

    /**
     * @param string[] $tags
     */
    public function withTags(array $tags): self
    {
        $new = clone $this;
        $new->tags = array_filter($tags, function ($item) {
            return is_string($item);
        });

        return $new;
    }

    /**
     * @param string[] $tags
     */
    public function addTags(array $tags): self
    {
        $new = clone $this;
        $tags = array_merge($this->tags, $tags);

        return $new->withTags($tags);
    }
}
