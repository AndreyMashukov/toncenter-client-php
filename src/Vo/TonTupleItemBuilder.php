<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonTupleItemBuilder extends TonTupleItem
{
    public function __construct(public string $boc) {}
}
