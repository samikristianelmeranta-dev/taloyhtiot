<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Repository;

use Kilpailutus\Domain\Bid;

interface BidRepository
{
    public function find(int $id): ?Bid;

    public function save(Bid $bid): Bid;

    public function findByTenderAndContractor(int $tenderId, int $contractorId): ?Bid;

    /** Vain jätetyt (ei peruttuja) tarjoukset. @return list<Bid> */
    public function findSubmittedByTender(int $tenderId): array;

    public function countSubmittedByTender(int $tenderId): int;

    /** @return list<Bid> */
    public function findByContractor(int $contractorId): array;
}
