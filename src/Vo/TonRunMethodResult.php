<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonRunMethodResult
{
    public function __construct(
        public bool $ok,
        public int $exitCode,
        public int $gasUsed,
        public TonTupleReader $stack,
    ) {}

    public function isOk(): bool
    {
        return $this->ok && 0 === $this->exitCode;
    }

    /**
     * @param array<string, mixed> $envelope toncenter `/runGetMethod` result envelope
     */
    public static function fromToncenter(array $envelope): self
    {
        $stackRaw = $envelope['stack'] ?? [];
        $stack    = is_array($stackRaw) ? $stackRaw : [];

        $normalized = [];
        foreach ($stack as $entry) {
            if (is_array($entry) && \array_key_exists(0, $entry) && \array_key_exists(1, $entry)) {
                $normalized[] = [Wire::str($entry[0]), $entry[1]];
            }
        }

        return new self(
            ok: (bool) ($envelope['ok'] ?? false),
            exitCode: Wire::int($envelope['exit_code'] ?? null, -1),
            gasUsed: Wire::int($envelope['gas_used'] ?? null),
            stack: TonTupleReader::fromRawStack($normalized),
        );
    }
}
