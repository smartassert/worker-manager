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
        $id = (string) new Ulid();
        if ('' === $id) {
            throw new \RuntimeException('Generated id is empty');
        }

        return $id;
    }
}
