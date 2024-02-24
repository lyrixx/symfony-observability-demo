<?php

namespace App\Monolog\Processor;

use App\Monolog\Processor\Model\ExportExtraContextInterface;
use App\Monolog\Processor\Model\ToLogInterface;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class ModelProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        foreach ($context as $key => $value) {
            if ($value instanceof ExportExtraContextInterface) {
                $context = [
                    ...$context,
                    ...$value->getExtraContext(),
                ];
            }
            if ($value instanceof ToLogInterface) {
                $context[$key] = $value->toLog();
            }
        }

        return $record->with(context: $context);
    }
}
