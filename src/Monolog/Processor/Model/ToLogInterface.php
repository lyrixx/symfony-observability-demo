<?php

namespace App\Monolog\Processor\Model;

interface ToLogInterface
{
    /**
     * @return array<string, string|bool|int|float>
     */
    public function toLog(): array;
}
