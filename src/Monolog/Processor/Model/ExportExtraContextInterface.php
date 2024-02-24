<?php

namespace App\Monolog\Processor\Model;

interface ExportExtraContextInterface
{
    /**
     * @return array<string, string|bool|int|float>
     */
    public function getExtraContext(): array;
}
