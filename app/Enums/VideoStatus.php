<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isInFlight(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
