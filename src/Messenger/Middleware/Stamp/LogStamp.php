<?php

namespace App\Messenger\Middleware\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class LogStamp implements StampInterface
{
    public function __construct(
        public string $logUuid,
    ) {
    }
}
