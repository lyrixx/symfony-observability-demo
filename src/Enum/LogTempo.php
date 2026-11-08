<?php

namespace App\Enum;

enum LogTempo: string
{
    case Now = 'now';
    case ThirtyMinutes = '30m';
    case OneHour = '1h';
    case SixHours = '6h';

    public function label(): string
    {
        return match ($this) {
            self::Now => 'right now',
            self::ThirtyMinutes => 'the last 30 minutes',
            self::OneHour => 'the last hour',
            self::SixHours => 'the last 6 hours',
        };
    }

    public function windowInSeconds(): int
    {
        return match ($this) {
            self::Now => 0,
            self::ThirtyMinutes => 1_800,
            self::OneHour => 3_600,
            self::SixHours => 21_600,
        };
    }

    public function minLogCount(): int
    {
        return match ($this) {
            self::Now => 1,
            self::ThirtyMinutes => 150,
            self::OneHour => 250,
            self::SixHours => 600,
        };
    }

    public function maxLogCount(): int
    {
        return self::Now === $this ? 100 : $this->minLogCount();
    }
}
