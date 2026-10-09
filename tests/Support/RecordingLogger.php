<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<array-key, mixed>}>
     */
    private array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: string, message: string, context: array<array-key, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn(array $record): string => $record['level'] . ' ' . $record['message'], $this->records);
    }
}
