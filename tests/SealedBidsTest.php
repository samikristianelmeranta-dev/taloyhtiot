<?php

declare(strict_types=1);

namespace Kilpailutus\Tests;

use Kilpailutus\Domain\Exception\Forbidden;
use Kilpailutus\Domain\Exception\NotFound;
use Kilpailutus\Tests\Support\ServiceTestCase;

/** Palvelun tärkein lupaus: tarjoukset pysyvät suljettuina määräpäivään asti. */
final class SealedBidsTest extends ServiceTestCase
{
    public function test_owner_cannot_open_bids_before_deadline(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());

        $this->clock->set('2026-11-20T09:59:59Z');
        $this->expectDomainError(Forbidden::class, fn () => $this->bids->listForTender($this->board, $id));
    }

    public function test_admin_cannot_open_bids_before_deadline(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());

        $this->expectDomainError(Forbidden::class, fn () => $this->bids->listForTender($this->admin, $id));
    }

    public function test_bids_open_for_owner_exactly_at_deadline_sorted_by_price(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => '190000']));
        $this->bids->submit($this->contractorB, $id, $this->bidData(['price_excl_vat' => '175000,50']));

        $this->clock->set(self::AFTER_DEADLINE);
        $bids = $this->bids->listForTender($this->board, $id);

        $this->assertSame([17500050, 19000000], array_column($bids, 'price_excl_vat_cents'));
        $this->assertContains('bids_viewed', $this->audit->events());
    }

    public function test_contractor_never_sees_other_bids(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());
        $this->bids->submit($this->contractorB, $id, $this->bidData());

        $this->expectDomainError(Forbidden::class, fn () => $this->bids->listForTender($this->contractorA, $id));
        $this->clock->set(self::AFTER_DEADLINE);
        $this->expectDomainError(Forbidden::class, fn () => $this->bids->listForTender($this->contractorA, $id));
    }

    public function test_contractor_sees_own_bid_only(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => '1000']));

        $this->assertSame(100000, $this->bids->own($this->contractorA, $id)['price_excl_vat_cents']);
        $this->expectDomainError(NotFound::class, fn () => $this->bids->own($this->contractorB, $id));
    }

    public function test_bid_count_is_visible_to_owner_but_not_to_contractors(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());

        $this->assertSame(1, $this->tenders->get($this->board, $id)['bid_count']);
        $this->assertArrayNotHasKey('bid_count', $this->tenders->get($this->contractorB, $id));
    }

    public function test_bids_of_tender_cancelled_before_deadline_stay_sealed_forever(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());
        $this->tenders->cancel($this->board, $id);

        $this->clock->set('2027-01-01T00:00:00Z');
        $this->expectDomainError(Forbidden::class, fn () => $this->bids->listForTender($this->board, $id));
        $this->expectDomainError(Forbidden::class, fn () => $this->bids->listForTender($this->admin, $id));
    }

    public function test_other_housing_company_cannot_see_tender_or_bids(): void
    {
        $id = $this->publishedTenderId();
        $this->clock->set(self::AFTER_DEADLINE);

        $this->expectDomainError(NotFound::class, fn () => $this->tenders->get($this->otherBoard, $id));
        $this->expectDomainError(NotFound::class, fn () => $this->bids->listForTender($this->otherBoard, $id));
    }

    public function test_audit_log_never_contains_bid_contents(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => '123456']));

        $this->assertStringNotContainsString('123456', json_encode($this->audit->entries));
    }
}
