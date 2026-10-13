<?php

declare(strict_types=1);

namespace Kilpailutus\Tests\Support;

use Kilpailutus\Domain\Repository\TenderRepository;
use Kilpailutus\Domain\Tender;
use Kilpailutus\Domain\TenderStatus;

final class InMemoryTenderRepository implements TenderRepository
{
    /** @var array<int, Tender> */
    private array $rows = [];
    private int $nextId = 1;

    public function find(int $id): ?Tender
    {
        return isset($this->rows[$id]) ? clone $this->rows[$id] : null;
    }

    public function save(Tender $tender): Tender
    {
        $tender->id ??= $this->nextId++;
        $this->rows[$tender->id] = clone $tender;

        return $tender;
    }

    public function findByOwner(int $ownerId): array
    {
        return $this->filter(fn (Tender $t) => $t->ownerId === $ownerId);
    }

    public function findOpen(\DateTimeImmutable $now): array
    {
        return $this->filter(fn (Tender $t) => $t->status === TenderStatus::Published && $t->deadlineAt > $now);
    }

    public function findByIds(array $ids): array
    {
        return $this->filter(fn (Tender $t) => in_array($t->id, $ids, true));
    }

    public function findAll(): array
    {
        return $this->filter(fn () => true);
    }

    /** @return list<Tender> */
    private function filter(callable $fn): array
    {
        return array_values(array_map(fn ($t) => clone $t, array_filter($this->rows, $fn)));
    }
}
