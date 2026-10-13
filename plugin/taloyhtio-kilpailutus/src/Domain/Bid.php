<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

use DateTimeImmutable;

/** Urakoitsijan tarjous. Rahasummat sentteinä (kokonaisluku), ei liukulukuina. */
final class Bid
{
    public function __construct(
        public ?int $id,
        public int $tenderId,
        public int $contractorId,
        public int $priceExclVatCents,
        public ?DateTimeImmutable $startDate,
        public ?DateTimeImmutable $completionDate,
        public int $warrantyMonths,
        public string $description,
        public string $referencesText,
        public BidStatus $status,
        public DateTimeImmutable $submittedAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isOwnedBy(Actor $actor): bool
    {
        return $actor->userId === $this->contractorId;
    }
}
