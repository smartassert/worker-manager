<?php

namespace App\Exception;

interface RecoverableDeciderExceptionInterface
{
    public function isRecoverable(): bool;
}
