<?php

declare(strict_types=1);

namespace Kilpailutus\Tests\Support;

use Kilpailutus\Domain\Bid;
use Kilpailutus\Domain\BidStatus;
use Kilpailutus\Domain\Repository\BidRepository;

final class InMemoryBidRepository implements BidRepository
{
    /** @var array<int, Bid> */
    private array $rows = [];
    private int $nextId = 1;

    public function find(int $id): ?Bid
    {
        return isset($this->rows[$id]) ? clone $this->rows[$id] : null;
    }

    public function save(Bid $bid): Bid
    {
        $bid->id ??= $this->nextId++;
        $this->rows[$bid->id] = clone $bid;

        return $bid;
    }

    public function findByTenderAndContractor(int $tenderId, int $contractorId): ?Bid
    {
        return $this->filter(fn (Bid $b) => $b->tenderId === $tenderId && $b->contractorId === $contractorId)[0] ?? null;
    }

    public function findSubmittedByTender(int $tenderId): array
    {
        return $this->filter(fn (Bid $b) => $b->tenderId === $tenderId && $b->status === BidStatus::Submitted);
    }

    public function countSubmittedByTender(int $tenderId): int
    {
        return count($this->findSubmittedByTender($tenderId));
    }

    public function findByContractor(int $contractorId): array
    {
        return $this->filter(fn (Bid $b) => $b->contractorId === $contractorId);
    }

    /** @return list<Bid> */
    private function filter(callable $fn): array
    {
        return array_values(array_map(fn ($b) => clone $b, array_filter($this->rows, $fn)));
    }
}
