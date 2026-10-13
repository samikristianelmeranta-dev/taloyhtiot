<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Repository;

use Kilpailutus\Domain\Tender;

interface TenderRepository
{
    public function find(int $id): ?Tender;

    /** Tallentaa uuden tai päivittää olemassa olevan. Palauttaa olion, jolla on id. */
    public function save(Tender $tender): Tender;

    /** @return list<Tender> */
    public function findByOwner(int $ownerId): array;

    /** Julkaistut kilpailutukset, joiden tarjousaika on käynnissä. @return list<Tender> */
    public function findOpen(\DateTimeImmutable $now): array;

    /** @param list<int> $ids @return list<Tender> */
    public function findByIds(array $ids): array;

    /** @return list<Tender> */
    public function findAll(): array;
}
