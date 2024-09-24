<?php

namespace App\Services;

use App\Message\MachineRequestInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class MachineRequestDispatcher
{
    /**
     * @param array<class-string, int> $dispatchDelays
     */
    public function __construct(
        private MessageBusInterface $messageBus,
        private array $dispatchDelays,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatch(MachineRequestInterface $request): Envelope
    {
        $dispatchDelay = $this->dispatchDelays[$request::class] ?? null;
        $stamps = [];

        if (is_int($dispatchDelay)) {
            $stamps[] = new DelayStamp($dispatchDelay);
        }

        return $this->messageBus->dispatch($request, $stamps);
    }

    /**
     * @param MachineRequestInterface[] $collection
     *
     * @return Envelope[]
     *
     * @throws ExceptionInterface
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
