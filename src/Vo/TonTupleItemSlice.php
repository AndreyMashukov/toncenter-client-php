<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonTupleItemSlice extends TonTupleItem
{
    public function __construct(public string $boc) {}
}
