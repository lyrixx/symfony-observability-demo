<?php

namespace App\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SapiProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['SAPI'] = \PHP_SAPI;
        $record->extra['uptime'] = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);

        if (\function_exists('cli_get_process_title')) {
            $record->extra['process_title'] = cli_get_process_title();
        }

        return $record;
    }
}
