<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonTupleItemTuple extends TonTupleItem
{
    /**
     * @param list<TonTupleItem> $items
     */
    public function __construct(public array $items) {}
}
