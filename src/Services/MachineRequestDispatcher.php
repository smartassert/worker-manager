<?php

namespace App\Services;

use App\Message\MachineRequestInterface;
use Symfony\Component\Messenger\Envelope;
use webignition\SymfonyMessengerMessageDispatcher\MessageDispatcher;

class MachineRequestDispatcher
{
    public function __construct(
        private MessageDispatcher $messageDispatcher
    ) {
    }

    public function dispatch(MachineRequestInterface $request): Envelope
    {
        return $this->messageDispatcher->dispatch($request);
    }

    /**
     * @param MachineRequestInterface[] $collection
     *
     * @return Envelope[]
     */
    public function dispatchCollection(array $collection): array
    {
        $envelopes = [];

        foreach ($collection as $request) {
            if ($request instanceof MachineRequestInterface) {
                $envelopes[] = $this->dispatch($request);
            }
        }

        return $envelopes;
    }
}
