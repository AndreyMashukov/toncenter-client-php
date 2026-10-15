<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

use Amashukov\Toncenter\TonRpcException;

final readonly class TonFeeEstimate
{
    /**
     * @param list<TonFeeBreakdown> $destinationFees
     */
    private function __construct(
        public TonFeeBreakdown $sourceFees,
        public array $destinationFees,
    ) {}

    public static function fromToncenter(mixed $row): self
    {
        if (!is_array($row) || !isset($row['source_fees']) || !isset($row['destination_fees'])
            || !is_array($row['destination_fees']) || !array_is_list($row['destination_fees'])) {
            throw new TonRpcException('A TON fee estimate requires source fees and a destination fee list.');
        }
        $destinations = [];
        foreach ($row['destination_fees'] as $fees) {
            $destinations[] = TonFeeBreakdown::fromToncenter($fees);
        }

        return new self(TonFeeBreakdown::fromToncenter($row['source_fees']), $destinations);
    }
}
