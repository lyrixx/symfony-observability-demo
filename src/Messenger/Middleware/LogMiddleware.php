<?php

namespace App\Messenger\Middleware;

use App\Messenger\Middleware\Stamp\LogStamp;
use App\Monolog\Processor\UuidProcessor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class LogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private UuidProcessor $uidProcessor,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(LogStamp::class);

        if ($stamp) {
            $this->uidProcessor->setUuid($stamp->logUuid);
        } else {
            $envelope = $envelope->with(new LogStamp($this->uidProcessor->getUuid()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
