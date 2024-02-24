<?php

namespace App\Dto;

use App\Monolog\Processor\Model\ToLogInterface;

class User implements ToLogInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toLog(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
