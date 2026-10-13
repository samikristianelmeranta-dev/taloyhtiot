<?php

declare(strict_types=1);

namespace Kilpailutus\Tests;

use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Exception\Conflict;
use Kilpailutus\Domain\Exception\Forbidden;
use Kilpailutus\Domain\Exception\ValidationError;
use Kilpailutus\Domain\Role;
use Kilpailutus\Tests\Support\ServiceTestCase;

final class BidSubmissionTest extends ServiceTestCase
{
    public function test_contractor_can_update_own_bid_until_deadline_one_bid_per_contractor(): void
    {
        $id = $this->publishedTenderId();
        $first = $this->bids->submit($this->contractorA, $id, $this->bidData());
        $second = $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => '170000']));

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(17000000, $second['price_excl_vat_cents']);
        $this->assertSame(1, $this->tenders->get($this->board, $id)['bid_count']);
        $this->assertSame(['bid_submitted', 'bid_updated'], array_values(array_filter(
            $this->audit->events(),
            fn ($e) => str_starts_with($e, 'bid_'),
        )));
    }

    public function test_bid_after_deadline_is_rejected(): void
    {
        $id = $this->publishedTenderId();
        $this->clock->set(self::AFTER_DEADLINE);

        $this->expectDomainError(Conflict::class, fn () => $this->bids->submit($this->contractorA, $id, $this->bidData()));
    }

    public function test_existing_bid_cannot_be_changed_or_withdrawn_after_deadline(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());
        $this->clock->set(self::AFTER_DEADLINE);

        $this->expectDomainError(Conflict::class, fn () => $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => '1'])));
        $this->expectDomainError(Conflict::class, fn () => $this->bids->withdraw($this->contractorA, $id));
    }

    public function test_only_contractors_can_bid(): void
    {
        $id = $this->publishedTenderId();

        $this->expectDomainError(Forbidden::class, fn () => $this->bids->submit($this->admin, $id, $this->bidData()));
    }

    public function test_owner_cannot_bid_on_own_tender_even_with_contractor_role(): void
    {
        $both = new Actor(1, [Role::HOUSING_COMPANY, Role::CONTRACTOR]);
        $id = $this->publishedTenderId();

        $this->expectDomainError(Conflict::class, fn () => $this->bids->submit($both, $id, $this->bidData()));
    }

    public function test_withdrawn_bid_is_not_counted_or_revealed_and_can_be_resubmitted(): void
    {
        $id = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $id, $this->bidData());
        $this->bids->withdraw($this->contractorA, $id);
        $this->assertSame(0, $this->tenders->get($this->board, $id)['bid_count']);

        $this->bids->submit($this->contractorA, $id, $this->bidData());
        $this->assertSame(1, $this->tenders->get($this->board, $id)['bid_count']);
    }

    public function test_price_parsing(): void
    {
        $id = $this->publishedTenderId();
        $cases = ['12 500,50' => 1250050, '12500.5' => 1250050, '99' => 9900, 7 => 700];
        foreach ($cases as $input => $cents) {
            $bid = $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => (string) $input]));
            $this->assertSame($cents, $bid['price_excl_vat_cents'], (string) $input);
        }
        foreach (['0', '-5', '1,234', 'abc', 12.5] as $bad) {
            $this->expectDomainError(ValidationError::class, fn () => $this->bids->submit($this->contractorA, $id, $this->bidData(['price_excl_vat' => $bad])));
        }
    }

    public function test_completion_must_be_after_start(): void
    {
        $id = $this->publishedTenderId();

        $this->expectDomainError(ValidationError::class, fn () => $this->bids->submit($this->contractorA, $id, $this->bidData([
            'start_date' => '2027-06-01',
            'completion_date' => '2027-05-01',
        ])));
    }
}
