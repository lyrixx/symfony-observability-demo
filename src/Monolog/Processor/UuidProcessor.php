<?php

namespace App\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

final class UuidProcessor implements ProcessorInterface, ResetInterface
{
    private string $uuid;

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $this->initUuid();

        $record->extra['log_uuid'] = $this->uuid;

        return $record;
    }

    public function getUuid(): string
    {
        $this->initUuid();

        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function reset(): void
    {
        unset($this->uuid);
    }

    private function initUuid(): void
    {
        if (isset($this->uuid)) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            $this->uuid = uuid_create();

            return;
        }

        $uuid = $request->headers->get('X-Request-Id', '');
        if (!uuid_is_valid($uuid)) {
            $this->uuid = uuid_create();

            return;
        }

        $this->uuid = $uuid;
    }
}
