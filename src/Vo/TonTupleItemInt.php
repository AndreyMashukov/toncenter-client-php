<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonTupleItemInt extends TonTupleItem
{
    /**
     * @param numeric-string $value decimal-string (bigint-safe, may exceed PHP_INT_MAX)
     */
    public function __construct(public string $value) {}
}
