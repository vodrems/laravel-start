<?php

namespace App\Enums;

enum DeletionReason: string
{
    case Manual = 'manual';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'удалён вручную',
            self::Expired => 'истёк срок хранения',
        };
    }
}
