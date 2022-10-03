<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerAsserter
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function getEnvelopeAtPosition(int $index): Envelope
    {
        $envelope = null;

        $queue = $this->transport->get();
        foreach ($queue as $queueIndex => $currentEnvelope) {
            if ($queueIndex === $index) {
                $envelope = $currentEnvelope;
            }
        }

        TestCase::assertInstanceOf(Envelope::class, $envelope);
        if (!$envelope instanceof Envelope) {
            throw new \InvalidArgumentException('No envelope at position ' . (string) $index);
        }

        return $envelope;
    }

    public function assertQueueIsEmpty(): void
    {
        $this->assertQueueCount(0);
    }

    public function assertQueueCount(int $expectedCount): void
    {
        TestCase::assertCount($expectedCount, $this->transport->get());
    }

    public function assertMessageAtPositionEquals(int $index, object $expectedMessage): void
    {
        TestCase::assertEquals(
            $expectedMessage,
            $this->getEnvelopeAtPosition($index)->getMessage()
        );
    }

    public function assertEnvelopeNotContainsStampsOfType(Envelope $envelope, string $type): void
    {
        $stamps = $envelope->all();
        TestCase::assertArrayNotHasKey($type, $stamps);
    }

    public function assertEnvelopeContainsStamp(
        Envelope $envelope,
        StampInterface $expectedStamp,
        int $expectedStampIndex
    ): void {
        $stamps = $envelope->all();
        $typeIndex = get_class($expectedStamp);

        TestCase::assertArrayHasKey($typeIndex, $stamps);

        $typeStamps = $stamps[$typeIndex];
        $actualStamp = $typeStamps[$expectedStampIndex] ?? null;

        TestCase::assertEquals($expectedStamp, $actualStamp);
    }

    /**
     * @param array<string, array<int, StampInterface>> $expectedEnvelopeContainsStampCollections
     */
    public function assertEnvelopeContainsStampCollections(
        Envelope $envelope,
        array $expectedEnvelopeContainsStampCollections
    ): void {
        foreach ($expectedEnvelopeContainsStampCollections as $expectedStampCollection) {
            foreach ($expectedStampCollection as $expectedStampIndex => $expectedStamp) {
                $this->assertEnvelopeContainsStamp($envelope, $expectedStamp, $expectedStampIndex);
            }
        }
    }
}
