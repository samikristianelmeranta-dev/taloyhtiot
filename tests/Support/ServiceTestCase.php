<?php

declare(strict_types=1);

namespace Kilpailutus\Tests\Support;

use Kilpailutus\Application\BidService;
use Kilpailutus\Application\TenderService;
use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Role;
use PHPUnit\Framework\TestCase;

abstract class ServiceTestCase extends TestCase
{
    /** Aloitushetki: 1.11.2026 klo 12 Suomen aikaa. */
    protected const NOW = '2026-11-01T10:00:00Z';
    /** Määräpäivä 20.11.2026 klo 12 Suomen aikaa = 10:00 UTC. */
    protected const DEADLINE_LOCAL = '2026-11-20T12:00';
    protected const AFTER_DEADLINE = '2026-11-20T10:00:00Z';

    protected FixedClock $clock;
    protected InMemoryTenderRepository $tenderRepo;
    protected InMemoryBidRepository $bidRepo;
    protected InMemoryAuditLog $audit;
    protected TenderService $tenders;
    protected BidService $bids;

    protected Actor $board;
    protected Actor $otherBoard;
    protected Actor $contractorA;
    protected Actor $contractorB;
    protected Actor $admin;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(self::NOW);
        $this->tenderRepo = new InMemoryTenderRepository();
        $this->bidRepo = new InMemoryBidRepository();
        $this->audit = new InMemoryAuditLog();
        $this->tenders = new TenderService($this->tenderRepo, $this->bidRepo, $this->audit, $this->clock);
        $this->bids = new BidService($this->tenderRepo, $this->bidRepo, $this->audit, $this->clock);

        $this->board = new Actor(1, [Role::HOUSING_COMPANY]);
        $this->otherBoard = new Actor(2, [Role::HOUSING_COMPANY]);
        $this->contractorA = new Actor(10, [Role::CONTRACTOR]);
        $this->contractorB = new Actor(11, [Role::CONTRACTOR]);
        $this->admin = new Actor(99, [Role::ADMIN]);
    }

    /** @return array<string, mixed> */
    protected function tenderData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Ikkunoiden ja parvekeovien uusiminen',
            'renovation_type' => 'ikkunat',
            'description' => "48 huoneistoa, 3 porrasta.\nPuu-alumiini-ikkunat.",
            'housing_company' => 'As Oy Esimerkki',
            'street_address' => 'Esimerkkikatu 1',
            'postal_code' => '00100',
            'municipality' => 'Helsinki',
            'desired_start' => '2027-05-01',
            'desired_end' => '2027-08-31',
            'deadline' => self::DEADLINE_LOCAL,
        ];
    }

    /** @return array<string, mixed> */
    protected function bidData(array $overrides = []): array
    {
        return $overrides + [
            'price_excl_vat' => '185000,00',
            'start_date' => '2027-05-03',
            'completion_date' => '2027-08-15',
            'warranty_months' => 24,
            'description' => 'Sisältää vanhojen ikkunoiden purun ja kierrätyksen.',
            'references' => 'As Oy Malli 2025',
        ];
    }

    protected function publishedTenderId(): int
    {
        $id = $this->tenders->create($this->board, $this->tenderData())['id'];
        $this->tenders->publish($this->board, $id);

        return $id;
    }

    protected function expectDomainError(string $class, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e, $e->getMessage());

            return;
        }
        $this->fail("Odotettiin poikkeusta {$class}");
    }
}
