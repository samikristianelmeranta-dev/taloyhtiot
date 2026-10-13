<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use Kilpailutus\Domain\Clock;
use Kilpailutus\Domain\Repository\AuditLog;

final class WpdbAuditLog implements AuditLog
{
    public function __construct(private readonly \wpdb $db, private readonly Clock $clock)
    {
    }

    public function record(string $event, int $actorId, ?int $tenderId = null, ?int $bidId = null, array $context = []): void
    {
        $this->db->insert(
            Schema::table('audit_log'),
            [
                'event' => $event,
                'actor_id' => $actorId,
                'tender_id' => $tenderId,
                'bid_id' => $bidId,
                'context' => $context === [] ? null : wp_json_encode($context),
                'created_at' => Rows::fromDateTime($this->clock->now()),
            ],
            ['%s', '%d', '%d', '%d', '%s', '%s'],
        );
    }
}
