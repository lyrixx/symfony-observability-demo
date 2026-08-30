<?php

namespace App\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Lets a log call ask for a specific "datetime" instead of "now", by passing
 * a `backdate_to` key in its context. Used by the demo's log generator to
 * simulate logs spread across a past time window.
 */
final class BackdateProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $backdateTo = $record->context['backdate_to'] ?? null;

        if (!$backdateTo instanceof \DateTimeImmutable) {
            return $record;
        }

        $context = $record->context;
        unset($context['backdate_to']);

        return $record->with(datetime: $backdateTo, context: $context);
    }
}
