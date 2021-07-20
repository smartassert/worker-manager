<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Ulid;

class UniqueId
{
    public static function create(): string
    {
        return (string) new Ulid();
    }
}
