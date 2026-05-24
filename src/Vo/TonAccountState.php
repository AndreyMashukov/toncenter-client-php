<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

enum TonAccountState: string
{
    case Active        = 'active';
    case Uninitialized = 'uninitialized';
    case Frozen        = 'frozen';

    public static function fromWire(string $raw): self
    {
        return match (strtolower($raw)) {
            'active'                                   => self::Active,
            'frozen'                                   => self::Frozen,
            '', 'uninit', 'uninitialized', 'nonexist'  => self::Uninitialized,
            default                                    => self::Uninitialized,
        };
    }
}
