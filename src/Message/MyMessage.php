<?php

namespace App\Message;

final class MyMessage
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
