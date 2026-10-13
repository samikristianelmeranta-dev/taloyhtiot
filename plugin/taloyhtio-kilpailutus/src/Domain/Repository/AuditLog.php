<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Repository;

/**
 * Tapahtumaloki luottamuksen tueksi: kuka avasi tarjoukset ja milloin.
 * Lokiin ei kirjata tarjousten sisältöä.
 */
interface AuditLog
{
    /** @param array<string, scalar|null> $context */
    public function record(string $event, int $actorId, ?int $tenderId = null, ?int $bidId = null, array $context = []): void;
}
