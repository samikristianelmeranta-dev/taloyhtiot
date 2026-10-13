<?php

declare(strict_types=1);

namespace Kilpailutus\Tests\Support;

use Kilpailutus\Domain\Repository\AuditLog;

final class InMemoryAuditLog implements AuditLog
{
    /** @var list<array{event:string, actor:int, tender:?int, bid:?int, context:array}> */
    public array $entries = [];

    public function record(string $event, int $actorId, ?int $tenderId = null, ?int $bidId = null, array $context = []): void
    {
        $this->entries[] = ['event' => $event, 'actor' => $actorId, 'tender' => $tenderId, 'bid' => $bidId, 'context' => $context];
    }

    /** @return list<string> */
    public function events(): array
    {
        return array_column($this->entries, 'event');
    }
}
