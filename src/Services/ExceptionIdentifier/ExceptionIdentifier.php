<?php

namespace App\Services\ExceptionIdentifier;

readonly class ExceptionIdentifier
{
    /**
     * @param iterable<ExceptionIdentifierInterface> $identifiers
     */
    public function __construct(
        private iterable $identifiers,
    ) {}

    public function isMachineNotFoundException(\Throwable $throwable): bool
    {
        foreach ($this->identifiers as $identifier) {
            if ($identifier->isMachineNotFoundException($throwable)) {
                return true;
            }
        }

        return false;
    }
}
