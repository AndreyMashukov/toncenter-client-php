<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

use LogicException;
use RuntimeException;

final class TonTupleReader
{
    private int $cursor = 0;

    /**
     * @param list<TonTupleItem> $items
     */
    public function __construct(
        private readonly array $items,
    ) {}

    /**
     * @param list<array{0: string, 1: mixed}> $rawStack toncenter raw stack rows ([type, value] pairs)
     */
    public static function fromRawStack(array $rawStack): self
    {
        $items = [];
        foreach ($rawStack as $entry) {
            $items[] = self::wireToItem($entry[0], $entry[1]);
        }

        return new self($items);
    }

    public function remaining(): int
    {
        return \count($this->items) - $this->cursor;
    }

    public function peek(): TonTupleItem
    {
        if ($this->cursor >= \count($this->items)) {
            throw new LogicException('TonTupleReader: cursor past end');
        }

        return $this->items[$this->cursor];
    }

    public function pop(): TonTupleItem
    {
        $item = $this->peek();
        ++$this->cursor;

        return $item;
    }

    /**
     * @return list<TonTupleItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return numeric-string decimal bigint string
     */
    public function readBigInt(): string
    {
        $item = $this->pop();
        if (!$item instanceof TonTupleItemInt) {
            throw new RuntimeException(sprintf('TonTupleReader: expected Int, got %s', $item::class));
        }

        return $item->value;
    }

    public function readNumber(): int
    {
        return (int) $this->readBigInt();
    }

    public function readCellBoc(): string
    {
        $item = $this->pop();
        if (!$item instanceof TonTupleItemCell) {
            throw new RuntimeException(sprintf('TonTupleReader: expected Cell, got %s', $item::class));
        }

        return $item->boc;
    }

    public function readSliceBoc(): string
    {
        $item = $this->pop();
        if (!$item instanceof TonTupleItemSlice) {
            throw new RuntimeException(sprintf('TonTupleReader: expected Slice, got %s', $item::class));
        }

        return $item->boc;
    }

    private static function wireToItem(string $type, mixed $value): TonTupleItem
    {
        return match (strtolower($type)) {
            'num', 'int'    => new TonTupleItemInt(self::decodeBigInt($value)),
            'cell'          => new TonTupleItemCell(self::decodeBocBase64($value)),
            'slice'         => new TonTupleItemSlice(self::decodeBocBase64($value)),
            'builder'       => new TonTupleItemBuilder(self::decodeBocBase64($value)),
            'tuple', 'list' => new TonTupleItemTuple(self::decodeTuple($value)),
            default         => new TonTupleItemNull(),
        };
    }

    /**
     * @return numeric-string decimal bigint string
     */
    private static function decodeBigInt(mixed $value): string
    {
        $raw = Wire::str($value);
        if ('' === $raw) {
            return '0';
        }
        if (str_starts_with($raw, '-0x') || str_starts_with($raw, '-0X')) {
            return self::asNumericString('-' . gmp_strval(gmp_init(substr($raw, 3), 16), 10));
        }
        if (str_starts_with($raw, '0x') || str_starts_with($raw, '0X')) {
            return self::asNumericString(gmp_strval(gmp_init(substr($raw, 2), 16), 10));
        }
        if (is_numeric($raw)) {
            return $raw;
        }

        return self::asNumericString(gmp_strval(gmp_init($raw, 16), 10));
    }

    /**
     * @return numeric-string
     */
    private static function asNumericString(string $value): string
    {
        if (!is_numeric($value)) {
            throw new RuntimeException(sprintf('TonTupleReader: gmp produced a non-numeric string "%s"', $value));
        }

        return $value;
    }

    private static function decodeBocBase64(mixed $value): string
    {
        if (is_array($value)) {
            return Wire::str($value['bytes'] ?? null);
        }

        return Wire::str($value);
    }

    /**
     * @return list<TonTupleItem>
     */
    private static function decodeTuple(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items   = [];
        $entries = $value['elements'] ?? $value;
        if (!is_array($entries)) {
            return [];
        }
        foreach ($entries as $entry) {
            if (is_array($entry) && \array_key_exists(0, $entry) && \array_key_exists(1, $entry)) {
                $items[] = self::wireToItem(Wire::str($entry[0]), $entry[1]);
            }
        }

        return $items;
    }
}
