<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\Tests\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class InMemoryLogger extends AbstractLogger implements LoggerInterface
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
