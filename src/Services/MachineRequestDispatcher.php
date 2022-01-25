<?php

namespace App\Services;

use App\Message\MachineRequestInterface;
use App\Message\StampedMessageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class MachineRequestDispatcher
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    public function dispatch(MachineRequestInterface $request): Envelope
    {
        $stamps = [];
        if ($request instanceof StampedMessageInterface) {
            $stamps = $request->getStamps();
            $request->clearStamps();
        }

        return $this->messageBus->dispatch($request, $stamps);
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
