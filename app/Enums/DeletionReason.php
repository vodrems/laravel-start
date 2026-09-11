<?php

namespace App\Enums;

enum DeletionReason: string
{
    case Manual = 'manual';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'deleted manually',
            self::Expired => 'retention period expired',
        };
    }
}
