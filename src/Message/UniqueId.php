<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Ulid;

class UniqueId
{
    /**
     * @return non-empty-string
     */
    public static function create(): string
    {
        return (string) new Ulid();
    }
}
