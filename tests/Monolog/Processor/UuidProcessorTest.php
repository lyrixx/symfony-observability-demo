<?php

namespace App\Tests\Monolog\Processor;

use App\Monolog\Processor\UuidProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class UuidProcessorTest extends TestCase
{
    public function testGetUuidReturnsAValidUuid(): void
    {
        $processor = new UuidProcessor(new RequestStack());

        self::assertTrue(uuid_is_valid($processor->getUuid()));
    }

    public function testGetUuidIsMemoizedUntilReset(): void
    {
        $processor = new UuidProcessor(new RequestStack());

        $uuid = $processor->getUuid();
        self::assertSame($uuid, $processor->getUuid());

        $processor->reset();

        self::assertNotSame($uuid, $processor->getUuid());
    }

    public function testSetUuidOverridesTheGeneratedOne(): void
    {
        $processor = new UuidProcessor(new RequestStack());

        $processor->setUuid('12345678-1234-1234-1234-123456789012');

        self::assertSame('12345678-1234-1234-1234-123456789012', $processor->getUuid());
    }

    public function testInvokeAddsTheUuidToTheRecordExtra(): void
    {
        $processor = new UuidProcessor(new RequestStack());
        $processor->setUuid('12345678-1234-1234-1234-123456789012');

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'A message',
        );

        $record = $processor($record);

        self::assertSame('12345678-1234-1234-1234-123456789012', $record->extra['log_uuid']);
    }
}
