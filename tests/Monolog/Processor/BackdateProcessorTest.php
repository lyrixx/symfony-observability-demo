<?php

namespace App\Tests\Monolog\Processor;

use App\Monolog\Processor\BackdateProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class BackdateProcessorTest extends TestCase
{
    public function testItRewritesTheDatetimeWhenBackdateToIsPresent(): void
    {
        $processor = new BackdateProcessor();
        $backdateTo = new \DateTimeImmutable('2020-01-01 00:00:00');

        $record = $processor($this->createRecord(['backdate_to' => $backdateTo]));

        self::assertSame($backdateTo, $record->datetime);
        self::assertArrayNotHasKey('backdate_to', $record->context);
    }

    public function testItLeavesTheRecordUntouchedWithoutBackdateTo(): void
    {
        $processor = new BackdateProcessor();
        $record = $this->createRecord();

        self::assertSame($record, $processor($record));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createRecord(array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'A message',
            context: $context,
        );
    }
}
