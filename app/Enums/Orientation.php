<?php

namespace App\Enums;

enum Orientation: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    public function width(): int
    {
        return match ($this) {
            self::Landscape => 1920,
            self::Portrait => 1080,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Landscape => 1080,
            self::Portrait => 1920,
        };
    }
}
